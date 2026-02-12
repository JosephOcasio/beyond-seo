output "ecr_repo_url" {
  value       = aws_ecr_repository.signer_repo.repository_url
  description = "ECR repository URL for signer image."
}

output "ecs_cluster_name" {
  value       = aws_ecs_cluster.scribe_cluster.name
  description = "ECS cluster name."
}

output "ecs_service_name" {
  value       = aws_ecs_service.scribe_service.name
  description = "ECS service name."
}

output "task_role_arn" {
  value       = aws_iam_role.ecs_task_role.arn
  description = "Task role ARN used by signer service."
}

output "execution_role_arn" {
  value       = aws_iam_role.ecs_task_execution_role.arn
  description = "Execution role ARN used by ECS agent."
}

output "kms_key_arn" {
  value       = var.kms_key_arn
  description = "Configured KMS key ARN."
}

output "secrets_manager_secret_arn" {
  value       = aws_secretsmanager_secret.scribe_internal_secret.arn
  description = "Secrets Manager ARN for INTERNAL_TASK_JWT."
}

output "alb_dns_name" {
  value       = local.alb_enabled ? aws_lb.scribe_lb[0].dns_name : null
  description = "ALB DNS name if ALB is enabled."
}

