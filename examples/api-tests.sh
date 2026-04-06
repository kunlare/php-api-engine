#!/bin/bash
# PHP CRUD API - Test Script
# Usage: bash examples/api-tests.sh

BASE_URL="http://localhost:8000/api/v1"

echo "============================================"
echo "  PHP CRUD API - Integration Tests"
echo "============================================"

# 1. Register first user (becomes admin)
echo -e "\n=== REGISTER ADMIN ==="
REGISTER_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","email":"admin@example.com","password":"Admin123!"}')
echo "$REGISTER_RESPONSE" | jq .

# 2. Login
echo -e "\n=== LOGIN ==="
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"Admin123!"}')
echo "$LOGIN_RESPONSE" | jq .

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token')
echo "Token: ${TOKEN:0:50}..."

# 3. Create API Key
echo -e "\n=== CREATE API KEY ==="
curl -s -X POST "$BASE_URL/auth/apikey" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Key"}' | jq .

# 4. List API Keys
echo -e "\n=== LIST API KEYS ==="
curl -s -X GET "$BASE_URL/auth/apikeys" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 5. List Users (admin)
echo -e "\n=== LIST USERS ==="
curl -s -X GET "$BASE_URL/users" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 6. Schema Management: create a table via API
echo -e "\n=== LIST TABLES ==="
curl -s -X GET "$BASE_URL/schema/tables" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo -e "\n=== CREATE PRODUCTS TABLE ==="
curl -s -X POST "$BASE_URL/schema/tables" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "table": "products",
    "columns": {
      "id":          {"type": "INT", "auto_increment": true, "primary": true},
      "name":        {"type": "VARCHAR", "length": 255, "nullable": false},
      "description": {"type": "TEXT"},
      "price":       {"type": "DECIMAL", "precision": 10, "scale": 2},
      "stock":       {"type": "INT", "default": "0"},
      "category":    {"type": "VARCHAR", "length": 100, "index": true},
      "created_at":  {"type": "TIMESTAMP", "default": "CURRENT_TIMESTAMP"},
      "updated_at":  {"type": "TIMESTAMP", "default": "CURRENT_TIMESTAMP", "on_update": "CURRENT_TIMESTAMP"}
    }
  }' | jq .

echo -e "\n=== GET TABLE STRUCTURE ==="
curl -s -X GET "$BASE_URL/schema/tables/products" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo -e "\n=== ADD COLUMN (sku) ==="
curl -s -X POST "$BASE_URL/schema/tables/products/columns" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"column": "sku", "definition": {"type": "VARCHAR", "length": 50, "unique": true}}' | jq .

echo -e "\n=== MODIFY COLUMN (name → longer) ==="
curl -s -X PATCH "$BASE_URL/schema/tables/products/columns/name" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"definition": {"type": "VARCHAR", "length": 500, "nullable": false}}' | jq .

echo -e "\n=== DROP COLUMN (sku) ==="
curl -s -X DELETE "$BASE_URL/schema/tables/products/columns/sku" \
  -H "Authorization: Bearer $TOKEN" | jq .

# 7. CRUD Operations on the products table
echo -e "\n=== CREATE PRODUCT ==="
curl -s -X POST "$BASE_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Laptop","description":"High-end laptop","price":999.99,"stock":50,"category":"Electronics"}' | jq .

echo -e "\n=== CREATE ANOTHER PRODUCT ==="
curl -s -X POST "$BASE_URL/products" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Mouse","description":"Wireless mouse","price":29.99,"stock":200,"category":"Accessories"}' | jq .

echo -e "\n=== LIST PRODUCTS ==="
curl -s -X GET "$BASE_URL/products" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo -e "\n=== GET PRODUCT BY ID ==="
curl -s -X GET "$BASE_URL/products/1" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo -e "\n=== FILTER PRODUCTS (price >= 500) ==="
curl -s -X GET "$BASE_URL/products/filter?price[gte]=500&order=price:desc" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo -e "\n=== UPDATE PRODUCT ==="
curl -s -X PATCH "$BASE_URL/products/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"price":899.99,"stock":45}' | jq .

echo -e "\n=== DELETE PRODUCT ==="
curl -s -X DELETE "$BASE_URL/products/2" \
  -H "Authorization: Bearer $TOKEN"
echo "(Expected: 204 No Content)"

# 8. Refresh Token
echo -e "\n\n=== REFRESH TOKEN ==="
REFRESH_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.refresh_token')
curl -s -X POST "$BASE_URL/auth/refresh" \
  -H "Content-Type: application/json" \
  -d "{\"refresh_token\":\"$REFRESH_TOKEN\"}" | jq .

echo -e "\n============================================"
echo "  Tests Complete"
echo "============================================"
