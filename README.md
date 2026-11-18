# RolForge
Web application for creating D&D 5e characters using data from 5eTools, with character export functionality for Foundry VTT through the RolForge Import module.

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

El resumen permite seleccionar habilidades de clase, variantes de dotes concedidas y las elecciones estructuradas de idioma, equipo y conjuros que el resolver reconoce. Las opciones se validan en el servidor y se conservan en sesión. El resumen distingue las decisiones pendientes de las advertencias: solo las primeras bloquean el guardado. No se comprueban requisitos de legalidad de las dotes.

Las concesiones fijas de dotes de trasfondo se identifican en `backgrounds.json`. Cuando el texto de la dote indica una elección de beneficios y el JSON aporta `_versions`, el resumen permite seleccionar una variante validada contra `feats.json`; la resolución incluye la referencia de la dote y la variante. Esto todavía no completa la resolución de sus efectos.

El resolver guarda referencias a los rasgos de clase y subclase del nivel actual, habilidades fijas y seleccionadas, algunas competencias fijas, idiomas, equipo y conjuros seleccionados. Calcula las habilidades, los PV iniciales de nivel 1 y la CA sin armadura a partir de las características; los objetos iniciales no se marcan automáticamente como equipados. En otros niveles se usa el valor predeterminado de PV si no puede calcularse. Las advertencias se conservan en `character_data` y no bloquean el guardado. El creador aún no interpreta todos los efectos, opciones de dotes, estructuras de equipo, clases preparadoras de conjuros o modificadores de combate: los datos guardados pueden requerir completar esas reglas. El modelo no constituye una exportación a Foundry o Plutonium.

El endpoint `Api/guardar-personaje.php` comprueba la sesión y el token CSRF, verifica las decisiones pendientes y guarda mediante una transacción en `characters` y las tablas `character_entities`, `character_choices`, `character_skills`, `character_proficiencies`, `character_spells`, `character_items` y `character_data`. El propietario se obtiene exclusivamente de la sesión. La ficha y el listado solo consultan personajes del usuario autenticado.

Para identificar las entidades se conserva la referencia `name|source` en `source_id`; en las subclases se añaden la clase, la fuente de clase y `shortName`. Es una clave de RolForge, no un identificador universal de 5etools. La ficha de un personaje almacenado permite consultar las descripciones de sus dotes desde `feats.json`.

El resumen y el endpoint usan el mismo resolver. Las selecciones del resumen se guardan en sesión al cambiarlas; el selector múltiple permite buscar, añadir y quitar opciones sin usar Ctrl. Sin JavaScript se ofrece un botón para guardar cada selección. El botón final espera a que se guarden los cambios, y el endpoint vuelve a resolver la sesión al recibir el POST, rechaza elecciones pendientes y guarda el modelo resultante en una transacción.

La ficha consulta las tablas persistentes, sin depender de la sesión de creación. Agrupa habilidades, competencias, conjuros, inventario y rasgos, y recupera las descripciones y niveles disponibles de los JSON locales. La CA sigue siendo la calculada sin armadura; los espacios mostrados son la capacidad conocida, no espacios restantes. Las advertencias internas permanecen almacenadas pero no se muestran en la ficha normal.

