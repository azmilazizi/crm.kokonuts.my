#!/bin/sh
set -e

BASE_URL="https://crm.kokonuts.my/"
ENV_FILE=".env"
EXAMPLE_FILE=".env.example"

update_key() {
  key="$1"
  value="$2"
  file="$3"
  if grep -q "^${key}=" "$file" 2>/dev/null; then
    sed -i -e "s#^${key}=.*#${key}=${value}#" "$file"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$file"
  fi
}

if [ -f "$ENV_FILE" ]; then
  update_key "APP_BASE_URL" "$BASE_URL" "$ENV_FILE"
else
  if [ -f "$EXAMPLE_FILE" ]; then
    cp "$EXAMPLE_FILE" "$ENV_FILE"
    update_key "APP_ENV" "production" "$ENV_FILE"
    update_key "APP_DEBUG" "false" "$ENV_FILE"
    update_key "APP_BASE_URL" "$BASE_URL" "$ENV_FILE"
  else
    printf 'APP_BASE_URL=%s\n' "$BASE_URL" > "$ENV_FILE"
  fi
fi

echo "APP_BASE_URL configured for $BASE_URL"
