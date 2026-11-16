#!/usr/bin/env bash
# =============================================================================
# deploy-app.sh  —  Despliegue de la infraestructura AWS por CODIGO.
#
# Valida y despliega la plantilla CloudFormation (VPC + RDS + ALB + Auto
# Scaling). Toda la infraestructura AWS se crea con "aws cloudformation deploy".
# Cloudflare se configura MANUALMENTE (ver docs/DESPLIEGUE.md) usando el
# output LoadBalancerDNS como destino del CNAME.
#
# Requisitos: AWS CLI v2 configurado (en Learner Lab: credenciales del lab).
#
# Uso:
#   cp deploy/deploy.env.example deploy/deploy.env   # y completa los secretos
#   set -a; source deploy/deploy.env; set +a
#   ./deploy/deploy-app.sh
#
# NUNCA se versiona deploy/deploy.env (esta en .gitignore).
# =============================================================================
set -Eeuo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="$HERE/cloudformation/pnk-infra.yaml"

STACK_NAME="${STACK_NAME:-pnk-eval3}"
REGION="${AWS_REGION:-us-east-1}"
ENVIRONMENT_NAME="${ENVIRONMENT_NAME:-pnk-prod}"

# ---- Secretos/entradas obligatorias (desde deploy/deploy.env) ----------------
: "${APP_REPO_URL:?Define APP_REPO_URL (URL HTTPS del repo publico)}"
: "${DB_MASTER_PASSWORD:?Define DB_MASTER_PASSWORD (min 12)}"
: "${DB_APP_PASSWORD:?Define DB_APP_PASSWORD (min 12)}"
: "${SESSION_SECRET:?Define SESSION_SECRET (min 16)}"
: "${LOG_PEPPER:?Define LOG_PEPPER (min 8)}"

APP_REPO_BRANCH="${APP_REPO_BRANCH:-feature/tercera-evaluacion}"
INSTANCE_PROFILE="${INSTANCE_PROFILE:-LabInstanceProfile}"
IA_API_KEY="${IA_API_KEY:-}"
SSL_CERT_ARN="${SSL_CERT_ARN:-}"

echo "==> [1/3] Validando plantilla CloudFormation"
aws cloudformation validate-template \
  --region "$REGION" \
  --template-body "file://$TEMPLATE" >/dev/null
echo "    Plantilla valida."

echo "==> [2/3] Desplegando stack '$STACK_NAME' en $REGION"
echo "    (crea/actualiza VPC, RDS, ALB y Auto Scaling; puede tardar ~10-15 min)"
aws cloudformation deploy \
  --region "$REGION" \
  --stack-name "$STACK_NAME" \
  --template-file "$TEMPLATE" \
  --no-fail-on-empty-changeset \
  --parameter-overrides \
    EnvironmentName="$ENVIRONMENT_NAME" \
    AppRepoUrl="$APP_REPO_URL" \
    AppRepoBranch="$APP_REPO_BRANCH" \
    InstanceProfileName="$INSTANCE_PROFILE" \
    DBMasterPassword="$DB_MASTER_PASSWORD" \
    DBAppPassword="$DB_APP_PASSWORD" \
    SessionSecret="$SESSION_SECRET" \
    LogPepper="$LOG_PEPPER" \
    IaApiKey="$IA_API_KEY" \
    SSLCertificateArn="$SSL_CERT_ARN"

echo "==> [3/3] Outputs del stack"
aws cloudformation describe-stacks \
  --region "$REGION" \
  --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[].{Clave:OutputKey,Valor:OutputValue}" \
  --output table

APP_URL=$(aws cloudformation describe-stacks --region "$REGION" --stack-name "$STACK_NAME" \
  --query "Stacks[0].Outputs[?OutputKey=='AppUrl'].OutputValue" --output text)
echo ""
echo "Listo. Verifica en unos minutos (cuando el ASG registre instancias sanas):"
echo "   curl -i ${APP_URL}health"
echo "   curl -i ${APP_URL}carta/1"
echo ""
echo "Para Cloudflare: usa el output 'LoadBalancerDNS' como destino del CNAME"
echo "(ver docs/DESPLIEGUE.md). La config de Cloudflare es MANUAL."
