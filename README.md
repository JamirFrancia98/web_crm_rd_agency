# CRM de Revolution Digital

Panel interno privado: usuarios, artículos del blog con SEO, datos de la empresa, mensajes recibidos y cartera de clientes.
Laravel 13 + Filament 5. El plan por fases está en `docs/crm/PLAN_CRM.md` de la carpeta de negocio.

## Primer arranque en local

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan crm:crear-admin
php artisan serve
```

Entrar en `http://127.0.0.1:8000/admin`. En el primer acceso el panel pide configurar la verificación en dos pasos.

## Comandos del blog

```bash
php artisan db:seed --class=CompanyProfileSeeder            # datos confirmados de la empresa (no pisa lo existente)
php artisan crm:importar-articulos <ruta>/blog_posts.json   # importa artículos (no pisa los existentes)
php artisan crm:exportar-articulos <carpeta>                # exporta los aprobados a .md con el formato de la web
php artisan crm:importar-redes <ruta>/social/linkedin       # piezas y calendario de LinkedIn (no pisa las existentes)
php artisan crm:importar-documentos <carpeta de negocio>    # biblioteca de documentos de solo lectura (lista cerrada de rutas)
php artisan crm:purgar-mensajes                             # borra mensajes vencidos (solo si hay plazo definido)
```

## Bandeja de mensajes del formulario

Apagada por defecto. Se activa en el `.env` **del servidor** (nunca en el repositorio ni en el chat), solo después de la revisión legal:

```
INBOX_ENABLED=true
INBOX_KEY=<clave larga y aleatoria creada por Jamir>
INBOX_RETENTION_DAYS=<plazo que fije el abogado>
```

El servidor de la web envía `POST /api/contacto` con la cabecera `X-Inbox-Key`. Sin consentimiento aceptado no se guarda nada.

## Reglas

- Nunca guardar contraseñas, claves ni datos reales de clientes en el repositorio (`.env` está ignorado).
- Las contraseñas se escriben solo en la terminal (`crm:crear-admin`) o en el panel.
- Requiere PHP 8.3 o superior. Pruebas: `php artisan test`.
- Nada se despliega sin aprobación expresa del propietario.
