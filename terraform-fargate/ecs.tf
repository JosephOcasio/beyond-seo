locals {
  alb_enabled = var.allow_public_alb && length(var.public_subnet_ids) > 0

  service_image = var.image_uri != "" ? var.image_uri : "${aws_ecr_repository.signer_repo.repository_url}:latest"
}

resource "aws_ecs_cluster" "scribe_cluster" {
  name = var.cluster_name
}

resource "aws_security_group" "svc_sg" {
  name        = "scribe-svc-sg-${var.environment}"
  description = "Security group for signer ECS service"
  vpc_id      = var.vpc_id

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_security_group" "alb_sg" {
  count       = local.alb_enabled ? 1 : 0
  name        = "scribe-alb-sg-${var.environment}"
  description = "Security group for signer ALB"
  vpc_id      = var.vpc_id

  dynamic "ingress" {
    for_each = toset(var.alb_ingress_cidrs)
    content {
      from_port   = 80
      to_port     = 80
      protocol    = "tcp"
      cidr_blocks = [ingress.value]
    }
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_security_group_rule" "allow_alb_to_service" {
  count                    = local.alb_enabled ? 1 : 0
  type                     = "ingress"
  from_port                = var.container_port
  to_port                  = var.container_port
  protocol                 = "tcp"
  security_group_id        = aws_security_group.svc_sg.id
  source_security_group_id = aws_security_group.alb_sg[0].id
}

resource "aws_lb" "scribe_lb" {
  count              = local.alb_enabled ? 1 : 0
  name               = "scribe-alb-${var.environment}"
  internal           = false
  load_balancer_type = "application"
  security_groups    = [aws_security_group.alb_sg[0].id]
  subnets            = var.public_subnet_ids
}

resource "aws_lb_target_group" "scribe_tg" {
  count       = local.alb_enabled ? 1 : 0
  name        = "scribe-tg-${var.environment}"
  port        = var.container_port
  protocol    = "HTTP"
  target_type = "ip"
  vpc_id      = var.vpc_id

  health_check {
    enabled             = true
    protocol            = "HTTP"
    path                = "/health"
    matcher             = "200-399"
    healthy_threshold   = 2
    unhealthy_threshold = 2
    interval            = 30
    timeout             = 5
  }
}

resource "aws_lb_listener" "scribe_http" {
  count             = local.alb_enabled ? 1 : 0
  load_balancer_arn = aws_lb.scribe_lb[0].arn
  port              = 80
  protocol          = "HTTP"

  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.scribe_tg[0].arn
  }
}

locals {
  container_definitions = jsonencode([
    {
      name      = "scribe-signer",
      image     = local.service_image,
      essential = true,
      portMappings = [
        {
          containerPort = var.container_port,
          hostPort      = var.container_port,
          protocol      = "tcp"
        }
      ],
      environment = [
        { name = "ENVIRONMENT", value = var.environment },
        { name = "AWS_REGION", value = var.aws_region },
        { name = "KMS_KEY_ARN", value = var.kms_key_arn }
      ],
      secrets = [
        {
          name      = "INTERNAL_TASK_JWT",
          valueFrom = "${aws_secretsmanager_secret.scribe_internal_secret.arn}:INTERNAL_TASK_JWT::"
        }
      ],
      logConfiguration = {
        logDriver = "awslogs",
        options = {
          awslogs-group         = aws_cloudwatch_log_group.ecs_task_log.name,
          awslogs-region        = var.aws_region,
          awslogs-stream-prefix = "scribe-signer"
        }
      }
    }
  ])
}

resource "aws_ecs_task_definition" "scribe_task" {
  family                   = "scribe-signer-${var.environment}"
  network_mode             = "awsvpc"
  requires_compatibilities = ["FARGATE"]
  cpu                      = tostring(var.task_cpu)
  memory                   = tostring(var.task_memory)
  execution_role_arn       = aws_iam_role.ecs_task_execution_role.arn
  task_role_arn            = aws_iam_role.ecs_task_role.arn
  container_definitions    = local.container_definitions

  runtime_platform {
    operating_system_family = "LINUX"
    cpu_architecture        = "X86_64"
  }
}

resource "aws_ecs_service" "scribe_service" {
  name            = "scribe-service-${var.environment}"
  cluster         = aws_ecs_cluster.scribe_cluster.id
  task_definition = aws_ecs_task_definition.scribe_task.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = var.private_subnet_ids
    security_groups  = [aws_security_group.svc_sg.id]
    assign_public_ip = false
  }

  dynamic "load_balancer" {
    for_each = local.alb_enabled ? [1] : []
    content {
      target_group_arn = aws_lb_target_group.scribe_tg[0].arn
      container_name   = "scribe-signer"
      container_port   = var.container_port
    }
  }

  depends_on = [
    aws_iam_role_policy_attachment.exec_role_managed,
    aws_iam_role_policy_attachment.attach_task_policy,
    aws_lb_listener.scribe_http
  ]
}

