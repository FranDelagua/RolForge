# RolForge
Una aplicación web, que esta conectada con 5eTools para la creación de personajes, luego los personajes tienen un botón de exportar, con el modulo (todavia en proceso) RolForgeImporter en foundryVVT se podrán importar los personajes creados en la web en la aplicación.
## 🚀 Tecnologías 🚀
- MySQL
- HTML
- CSS
- JavaScript
## ✨ Funcionalidades ✨
- Sistema de usuarios con login y registro
- Creación de personajes con 5eTools
- Exportador de personajes.
## 🛠️ Instalación 🛠️
- Cuando el programa este listo, pondré el enlace al dominio web para que se pueda registrar y crear personajes gratuitamente.

### Migración de cuentas

Antes de usar el login o el registro con la nueva base de datos, haz una copia de seguridad y, con la aplicación detenida, ejecuta `php Api/migrar-usuarios.php`. La migración requiere que `users` esté vacía y que no haya personajes en la tabla antigua `personajes`; si los hay, se detiene porque todavía no existe una migración de esos personajes. Añade `users.foto_perfil` si falta y copia las cuentas, identificadores, hashes y fotos sin borrar los registros antiguos. El cambio de esquema de MySQL no se revierte si falla la copia, aunque la copia de cuentas sí se ejecuta en una transacción. Las sesiones antiguas deben volver a iniciar sesión.

La conexión usa por defecto la base `rolforge` en `localhost` con el usuario `root` y contraseña vacía; puede configurarse con `ROLFORGE_DB_HOST`, `ROLFORGE_DB_NAME`, `ROLFORGE_DB_USER` y `ROLFORGE_DB_PASSWORD`.
## 👨‍💻 Autor 👨‍💻 
- Fran Heredia

## Personaje en creación

El creador conserva en sesión referencias a las entidades locales de 5etools: `tipo`, `name` y `source`. Las subclases incluyen también `shortName`, `className` y `classSource`, necesarios para distinguir las versiones de una misma clase. La clave `name|source` se reconstruye cuando se necesita; no se presenta como un identificador nativo universal de 5etools.

Las decisiones raciales y sus dotes se guardan bajo `raza.elecciones`, y las dotes contienen sus propias `elecciones`. Las mejoras de características de las dotes raciales se leen de `feats.json`, se validan y se conservan con la dote. Las bonificaciones se guardan separadas de los atributos base. Las sesiones del formato anterior se adaptan al abrir el creador; las descripciones y demás datos completos siguen en los JSON locales.

El creador aún no resuelve todas las decisiones ni valida los requisitos y demás elecciones internas de las dotes. El resumen es provisional y no constituye una exportación a Foundry o Plutonium.

