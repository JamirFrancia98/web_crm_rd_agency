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

## Reglas

- Nunca guardar contraseñas, claves ni datos reales de clientes en el repositorio (`.env` está ignorado).
- Las contraseñas se escriben solo en la terminal (`crm:crear-admin`) o en el panel.
- Requiere PHP 8.3 o superior. Pruebas: `php artisan test`.
- Nada se despliega sin aprobación expresa del propietario.
