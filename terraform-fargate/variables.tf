variable "aws_region" {
  description = "AWS region for all resources."
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Deployment environment identifier (dev/stage/prod)."
  type        = string
  default     = "dev"
}

variable "cluster_name" {
  description = "ECS cluster name."
  type        = string
  default     = "scribe-cluster"
}

variable "vpc_id" {
  description = "VPC ID where ECS tasks run."
  type        = string
}

variable "private_subnet_ids" {
  description = "Private subnet IDs for ECS service."
  type        = list(string)
}

variable "public_subnet_ids" {
  description = "Public subnet IDs for ALB (required if allow_public_alb=true)."
  type        = list(string)
  default     = []
}

variable "kms_key_arn" {
  description = "Asymmetric KMS key ARN used by signer service."
  type        = string
}

variable "image_uri" {
  description = "Container image URI. Leave empty to default to created ECR repo :latest."
  type        = string
  default     = ""
}

variable "container_port" {
  description = "Container listening port."
  type        = number
  default     = 9000
}

variable "desired_count" {
  description = "Desired ECS task count."
  type        = number
  default     = 1
}

variable "task_cpu" {
  description = "Fargate task CPU units."
  type        = number
  default     = 512
}

variable "task_memory" {
  description = "Fargate task memory (MiB)."
  type        = number
  default     = 1024
}

variable "allow_public_alb" {
  description = "If true, creates an internet-facing ALB."
  type        = bool
  default     = false
}

variable "alb_ingress_cidrs" {
  description = "CIDR ranges allowed to reach ALB listener."
  type        = list(string)
  default     = ["0.0.0.0/0"]
}

variable "enable_autoscaling" {
  description = "Enable ECS service autoscaling."
  type        = bool
  default     = true
}

variable "autoscaling_min_capacity" {
  description = "Minimum task count when autoscaling is enabled."
  type        = number
  default     = 1
}

variable "autoscaling_max_capacity" {
  description = "Maximum task count when autoscaling is enabled."
  type        = number
  default     = 4
}

variable "autoscaling_target_cpu_percent" {
  description = "CPU utilization target for autoscaling."
  type        = number
  default     = 50
}

variable "internal_task_jwt" {
  description = "Optional initial INTERNAL_TASK_JWT value. Prefer setting via AWS console/CLI."
  type        = string
  sensitive   = true
  default     = ""
}

