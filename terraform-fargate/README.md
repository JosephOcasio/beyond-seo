# Terraform Fargate Module (Signer Service)

Deploys a production-lean ECS/Fargate signer service with:
- ECS Cluster, Task Definition, Service
- ECR repository
- CloudWatch logs
- IAM least-privilege task role for KMS signing
- Secrets Manager integration for `INTERNAL_TASK_JWT`
- Optional ALB
- Optional autoscaling

## Prerequisites

- Existing asymmetric KMS key ARN (`SIGN_VERIFY`, ECC P-256 recommended)
- Existing VPC + subnets
- Terraform >= 1.5
- AWS credentials with IAM/ECS/Logs/ECR/Secrets permissions

## Quick start

```bash
cd /Users/josephocasio/Documents/New\ project/terraform-fargate
cp terraform.tfvars.example terraform.tfvars
# edit terraform.tfvars
terraform init
terraform plan
terraform apply
```

## Notes

- If `image_uri` is empty, service defaults to `${ecr_repo_url}:latest`.
- Secret injection uses JSON key selector:
  - `valueFrom = "<secret-arn>:INTERNAL_TASK_JWT::"`
- Keep `internal_task_jwt` empty in Terraform for production; set secret value post-deploy via AWS CLI/console.
- For CI/CD persistence, use a remote Terraform backend (S3 + DynamoDB lock table).

