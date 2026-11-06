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
```

## Reglas

- Nunca guardar contraseñas, claves ni datos reales de clientes en el repositorio (`.env` está ignorado).
- Las contraseñas se escriben solo en la terminal (`crm:crear-admin`) o en el panel.
- Requiere PHP 8.3 o superior. Pruebas: `php artisan test`.
- Nada se despliega sin aprobación expresa del propietario.
