# Lim Media

## Klaviyo/Linnworks Integration

### Initial Setup

Please fill below information in the .env file.

```env
LINNWORKS_API_URL=<API_URL>
LINNWORKS_APP_ID=<APP_ID>
LINNWORKS_APP_SECRET=<APP_SECRET>
KLAVIYO_API_URL=https://a.klaviyo.com/api/
...
APP_ENV=prod
APP_DEBUG=false
```

Run below commands (one time) for initialization

```bash
composer install # Laravel packages
npm install # Node modules
php artisan key:generate # Larave Key
php artisan migrate # Database setup
php artisan route:cache # Clean Route Cache
php artisan view:cache # Clean view Cache
```

## 🚀 Levantar en Localhost

### Opción 1: Usando Docker Compose 

```bash
# Construir y levantar los contenedores
docker-compose up -d

# Verificar que los contenedores estén corriendo
docker-compose ps
```

### Opción 2: Usando Docker directamente

```bash
# Construir la imagen
docker build -t klaviyo-lw .

# Ejecutar el contenedor
docker run -d -p 8000:80 --name klaviyo-lw-app klaviyo-lw
```

### Configuración adicional después de levantar

```bash
# Generar clave de aplicación
docker-compose exec app php artisan key:generate

# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Limpiar cachés
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
docker-compose exec app php artisan config:cache
```

## 🌐 Acceder a la aplicación

La aplicación estará disponible en:
- **URL local**: http://localhost:8000
- **URL de red**: http://0.0.0.0:8000

## 🔗 CONFIGURAR NGROK PARA DESARROLLO

### 1. Instalar ngrok

```bash
# Descargar ngrok desde https://ngrok.com/download
# O usar npm
npm install -g ngrok
```

### 2. Configurar ngrok

```bash
# Exponer el puerto local a internet
ngrok http 8000
```

### 3. Configurar la aplicación para usar la URL de ngrok

Una vez que ngrok esté corriendo, copia la URL HTTPS que te proporciona (ej: `https://abc123.ngrok.io`) y actualiza tu archivo `.env`: agregando /oauth/callback al final

(ej: `https://abc123.ngrok.free.app/oauth/callback`)

```env
KLAVIYO_REDIRECT_URI=https://abc123.ngrok.free.app/oauth/callback
```

### 4. Configuracion de la app en Klaviyo

Una vez dentro de klaviyo, en la seccion de "Manage apps" encontraran el listado con las apps, escogeran la correspondiente y en la seccion de "Redirect URLs" y pondran la URL que les dio ngrok agregando /oauth/callback al final

(ej: `https://abc123.ngrok.free.app/oauth/callback`) deben coincidir las URL para que funcione

### 6. Configuracion adicional .env

Asi debe de verse este apartado en tu .env con los claves correspondientes que te dio la app en Klaviyo


KLAVIYO_CLIENT_ID=#############
KLAVIYO_CLIENT_SECRET=##############
KLAVIYO_REDIRECT_URI=`https://abc123.ngrok.free.app/oauth/callback`


### 5. Iniciar servidor local

```bash
php artisan config:clear
```

```bash
php artisan serve
```
una vez aplicado los comandos, dirijirse a la URL antes mencionada

## 🛠️ Comandos útiles

```bash
# Ver logs de los contenedores
docker-compose logs -f

# Ejecutar comandos artisan dentro del contenedor
docker-compose exec app php artisan migrate
docker-compose exec app php artisan route:list

# Acceder al shell del contenedor
docker-compose exec app bash

# Detener los contenedores
docker-compose down

# Detener y eliminar volúmenes
docker-compose down -v
```

## 🔍 Troubleshooting

### Problemas comunes:

1. **Puerto 8000 ocupado**: Cambia `DOCKER_APP_PORT` en el `.env`
2. **Puerto 3307 ocupado**: Cambia `DOCKER_DB_PORT` en el `.env`
3. **Permisos de archivos**: Ejecuta `chmod -R 755 storage bootstrap/cache`
4. **Problemas de red**: Verifica que Docker esté corriendo correctamente

### Verificar estado:

```bash
# Verificar contenedores
docker-compose ps

# Verificar logs
docker-compose logs app

# Verificar conectividad de BD
docker-compose exec app php artisan tinker
```

## Mercado Libre + Linnworks (envíos)

Obtiene órdenes de Mercado Libre, actualiza envíos seller-fulfilled y sincroniza Full con Linnworks.

### .env

```env
MELI_CLIENT_ID=
MELI_CLIENT_SECRET=
MELI_REDIRECT_URI=http://localhost:9001/oauth/mercadolibre/callback
MELI_AUTH_HOST=https://auth.mercadolibre.com.mx
MELI_API_URL=https://api.mercadolibre.com
```

En [developers.mercadolibre.com](https://developers.mercadolibre.com) crea la app y pon el mismo Redirect URI.

### Uso

```bash
docker-compose exec app php artisan migrate

# Conectar cuenta ML (vendedor principal, no operador)
# http://localhost:9001/auth/mercadolibre
# opcional: ?linnwork_user_id=UUID-DEL-USUARIO-LINNWORKS

docker-compose exec app php artisan MercadoLibreTokenRefresh:task
docker-compose exec app php artisan MercadoLibreSync:task
docker-compose exec app php artisan MercadoLibreInventorySync:task
docker-compose exec app php artisan MercadoLibreInventorySync:task --create-listings
```

- **Seller-fulfilled (ME1 / custom):** si Linnworks ya tiene tracking, se notifica a ML como `shipped`.
- **Full (fulfillment):** ML manda el envío; solo se lee el status y, si hay match, se copia el tracking a Linnworks.
- No se marca `delivered` automáticamente (es irreversible en ML).
- **Órdenes:** el sync activo solo procesa órdenes abiertas; cerradas/canceladas se guardan pero salen del listado.
- **Inventory:** mapeo SKU (`mercadolibre_listings`). Ítem nuevo en ML → crea stock item en Linnworks. Stock **ida y vuelta**: gana el lado que cambió; si ambos cambian, Linnworks (almacén) gana. Título de Linnworks se empuja a ML en el mismo sync.
- **Create listings:** publica en Mercado Libre los SKUs de Linnworks que aún no tienen listing. Requiere título, precio > 0, stock ≥ 1 e imagen. Usa el predictor de categoría de ML. No corre en el cron (solo botón / `--create-listings`).
- Endpoints LW: `AddInventoryItem`, `GetStockItems`, `GetStockItemsFull`, `SetStockLevel`. Stock Full de ML no se gestiona aquí (solo publicaciones seller).

KL:C:D
