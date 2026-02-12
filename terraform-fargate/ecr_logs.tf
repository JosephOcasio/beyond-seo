resource "aws_ecr_repository" "signer_repo" {
  name = "scribe-signer-${var.environment}"

  image_scanning_configuration {
    scan_on_push = true
  }
}

resource "aws_cloudwatch_log_group" "ecs_task_log" {
  name              = "/ecs/scribe-signer-${var.environment}"
  retention_in_days = 30
}

