resource "aws_secretsmanager_secret" "scribe_internal_secret" {
  name                    = "scribe/internal/${var.environment}"
  description             = "Internal credentials for Scribe signer service."
  recovery_window_in_days = 7
}

resource "aws_secretsmanager_secret_version" "scribe_internal_secret_version" {
  count = var.internal_task_jwt != "" ? 1 : 0

  secret_id     = aws_secretsmanager_secret.scribe_internal_secret.id
  secret_string = jsonencode({ INTERNAL_TASK_JWT = var.internal_task_jwt })
}

