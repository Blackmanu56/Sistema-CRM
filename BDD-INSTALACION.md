# BDD.sql — instalación y actualización

`BDD.sql` es el archivo principal del esquema del CRM ADEMI. Sirve tanto para una base vacía como para una base compatible que ya tenga información. No crea ni selecciona la base: primero hay que seleccionarla en el cliente SQL.

```sh
mysql --default-character-set=utf8mb4 -u USUARIO -p NOMBRE_BASE < BDD.sql
```

Antes de actualizar una base existente, hacer y verificar un respaldo privado. El script agrega estructuras faltantes, conserva las tablas, columnas y registros existentes y puede ejecutarse nuevamente. Si detecta columnas incompatibles, se detiene con un mensaje para comparar el esquema antes de continuar; no intenta convertirlas automáticamente.

Requiere MariaDB 10.4 o posterior (probado en 10.4.32) y permisos para crear tablas, columnas, índices, claves foráneas y una rutina temporal de aplicación. No contiene empleados, clientes, credenciales ni datos de prueba. Las migraciones puntuales de `sql/migrations/` se conservan como historial; para instalaciones o actualizaciones nuevas se utiliza únicamente el `BDD.sql` de la raíz.
