resource "aws_iam_role" "ecs_task_execution_role" {
  name = "scribe-ecs-exec-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Service = "ecs-tasks.amazonaws.com"
        }
        Action = "sts:AssumeRole"
      }
    ]
  })
}

resource "aws_iam_role_policy_attachment" "exec_role_managed" {
  role       = aws_iam_role.ecs_task_execution_role.name
  policy_arn = "arn:${data.aws_partition.current.partition}:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

resource "aws_iam_role" "ecs_task_role" {
  name = "scribe-ecs-task-${var.environment}"

  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Principal = {
          Service = "ecs-tasks.amazonaws.com"
        }
        Action = "sts:AssumeRole"
      }
    ]
  })
}

data "aws_iam_policy_document" "task_policy_doc" {
  statement {
    sid     = "AllowSignerKMS"
    effect  = "Allow"
    actions = ["kms:Sign", "kms:GetPublicKey", "kms:DescribeKey", "kms:CreateGrant", "kms:ListGrants", "kms:RevokeGrant"]
    resources = [
      var.kms_key_arn
    ]
  }

  statement {
    sid    = "AllowReadSignerSecret"
    effect = "Allow"
    actions = [
      "secretsmanager:GetSecretValue",
      "secretsmanager:DescribeSecret"
    ]
    resources = [aws_secretsmanager_secret.scribe_internal_secret.arn]
  }
}

resource "aws_iam_policy" "task_policy" {
  name   = "scribe-ecs-task-policy-${var.environment}"
  policy = data.aws_iam_policy_document.task_policy_doc.json
}

resource "aws_iam_role_policy_attachment" "attach_task_policy" {
  role       = aws_iam_role.ecs_task_role.name
  policy_arn = aws_iam_policy.task_policy.arn
}

