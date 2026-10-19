#!/bin/bash
# Genera un certificado TLS autofirmado para PROBAR HTTPS en local (XAMPP/Apache).
# NO usar en producción: en producción use un certificado emitido por una CA
# (p. ej. Let's Encrypt / certbot). Uso: bash generar_cert_dev.sh
set -e
DEST="${1:-./tls}"
mkdir -p "$DEST"
openssl req -x509 -newkey rsa:2048 -keyout "$DEST/key.pem" -out "$DEST/cert.pem" \
  -days 365 -nodes -subj "/C=CL/O=PNK/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
echo "Certificado de desarrollo creado en $DEST/ (cert.pem + key.pem)."
echo "Configure Apache con config/apache-vhost-ssl.conf apuntando a estos archivos."
