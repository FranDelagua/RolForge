DOCUMENTO DE ARQUITECTURA Y ESPECIFICACIÓN TÉCNICA 

# Módulo RolForge Importer para Foundry VTT (D&D 5e) 

Diseño integral de exportación JSON, mapeo de datos, resolución de compendios e implementación en Foundry VTT v14 + dnd5e v6.x 

Nota de rigor y verificación de esquema: Esta especificación está calibrada para Foundry VTT v14 y el sistema D&D 5e ( dnd5e ) v6.x (con soporte para reglas 2014 y 2024). Dado que las subclaves internas dentro de actor.system e item.system evolucionan entre versiones mayores de dnd5e , se distinguen explícitamente las rutas confirmadas de los puntos donde conviene inspeccionar CONFIG.Actor.dataModels.character.schema y CONFIG.Item.dataModels en tu instancia activa de Foundry. 

## A. Fuentes Oficiales Utilizadas 

- Introducción al desarrollo en Foundry VTT: <u>https://foundryvtt.com/article/intro-development/</u> 

- Guía de desarrollo de módulos ( module.json , ES modules, hooks): <u>https://foundryvtt.com/article/ module-development/</u> 

- Notas de versión de Foundry VTT (v14): <u>https://foundryvtt.com/releases/</u> 

- API Reference v14 (raíz): <u>https://foundryvtt.com/api/</u> 

- Clase Actor (v14): <u>https://foundryvtt.com/api/classes/foundry.documents.Actor.html</u> 

- Colección Actors (v14): <u>https://foundryvtt.com/api/classes/foundry.documents.collections.Actors.htm l</u> 

- Módulo foundry.documents (creación, embebidos, fromUuid , flags): <u>https://foundryvtt.com/api/m odules/foundry.documents.html</u> 

- Clase CompendiumCollection (v14): <u>https://foundryvtt.com/api/classes/foundry.documents.collection s.CompendiumCollection.html</u> 

- Clase ApplicationV2 (v14): <u>https://foundryvtt.com/api/classes/foundry.applications.api.ApplicationV 2.html</u> 

- Clase DialogV2 (v14): <u>https://foundryvtt.com/api/classes/foundry.applications.api.DialogV2.html</u> 

- Clase FilePicker ( foundry.applications.apps.FilePicker , v14): <u>https://foundryvtt.com/api/clas ses/foundry.applications.apps.FilePicker.html</u> 

- Paquete y repositorio oficial dnd5e : <u>https://foundryvtt.com/packages/dnd5e</u> y https://github.com/fou <u>ndryvtt/dnd5e</u> 

## B. Arquitectura General del Sistema 

El flujo conecta RolForge y Foundry VTT mediante un contrato JSON canónico agnóstico a la estructura interna de Foundry, evitando acoplar la aplicación web a los cambios internos de dnd5e : 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 1 of 13 

[RolForge Web App] ├── Exportar TXT   ──▶ Formato legible humano (.txt) └── Exportar JSON  ──▶ Esquema canónico RolForge ("rolforge-character") │ ▼ (Archivo .json seleccionado por el usuario) [Foundry VTT v14 + Sistema dnd5e v6.x] └── Módulo "rolforge-importer" ├── UI (ApplicationV2 / DialogV2) ──▶ Selección de archivo + Previsualización + Confirmación 

├── Validator ───────────────────────▶ Esquema, versión, sistema, rangos, seguridad ├── Compendium Resolver ──────────────▶ Resolución por UUID o búsqueda en compendios (getIndex / fromUuid) ├── Mapper / Transformer ────────────▶ Construcción de datos Actor + Items embebidos 

└── Persistence Engine ──────────────▶ Actor.create() + createEmbeddedDocuments("Item", ...) + Flags 

- Desacoplamiento total: RolForge exporta conceptos del dominio D&D 5e (atributos base, 

- especie/raza, clase, nivel, equipo, hechizos, rasgos) sin depender de los DataModel privados de dnd5e . 

- Resolución híbrida (Compendio primero, fallback a datos propios): Si el personaje referencia una 

- entidad conocida (por ejemplo "Longsword" o un UUID explícito), el módulo intenta resolverla en compendios antes de fabricar un Item básico desde los campos exportados. 

- Persistencia controlada y trazabilidad: Se crea primero el Actor ( type: "character" ), luego se embeben los Item s en lote mediante actor.createEmbeddedDocuments("Item", ...) y se guardan metadatos/advertencias en flags["rolforge-importer"] . 

## 1. APIs y Sistemas Oficiales de Foundry VTT 

|API / Sistema|Función y Uso en el Módulo|Versión|Alternativa Moderna|
|---|---|---|---|
|Manifest<br>(<br>module.json )|Define<br>id , versión, compatibilidad,<br>system: ["dnd5e"] ,<br>esmodules ,<br>stylesy<br>languages .|v10 – v14+|Usar<br>"esmodules"en<br>lugar de<br>"scripts"<br>clásicos.|
|Hooks (<br>Hooks.on/<br>once )|Inicializa el módulo en<br>"init" /<br>"ready"e inyecta el botón<br>en<br>"renderActorDirectory" .|Todas|Mecanismo oficial<br>vigente de eventos del<br>núcleo.|
|ApplicationV2|Ventana principal de importación con<br>estado, selección de archivo, vista previa<br>del personaje y errores.|v12 – v14|Reemplaza a<br>Applicationy<br>FormApplication(v1<br>obsoletas).|
|DialogV2|Confirmaciones rápidas<br>(<br>DialogV2.confirm ) y alertas<br>modales ligeras.|v12 – v14|Reemplaza al antiguo<br>Dialog(v1).|
|FilePicker+<br><input<br>type="file">|foundry.applications.apps.FileP<br>ickernavega archivos en servidor;<br><input type="file">lee<br>file.text()directo del PC del<br>usuario.|v13 – v14|Usar<br>foundry.applicatio<br>ns.apps.FilePicker<br>en vez del global<br>obsoleto.|



RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 2 of 13 

|API / Sistema|Función y Uso en el Módulo|Versión|Alternativa Moderna|
|---|---|---|---|
|Actory<br>Item<br>embebidos|Actor.create(...)crea el<br>personaje y<br>actor.createEmbeddedDocuments("<br>Item", ...)adjunta inventario,<br>clases, hechizos y rasgos.|v10 – v14|Creación en lote con<br>createEmbeddedDocu<br>ments frente a llamadas<br>individuales.|
|Document Data<br>Models|Validan tipos y calculan datos derivados<br>(<br>prepareDerivedData ) como<br>modificadores y competencia.|v10 – v14|No sobrescribir valores<br>derivados; enviar<br>puntuaciones base.|
|CompendiumCollec<br>tiony<br>fromUuid|pack.getIndex({ fields })y<br>pack.getDocument(id)/<br>fromUuid(uuid)localizan ítems<br>oficiales.|v10 – v14|Indexado ligero antes de<br>descargar documentos<br>completos.|
|Permisos<br>(<br>game.user.can )|Verifica<br>game.user.can("ACTOR_CREATE")y<br>nivel de acceso a packs<br>(<br>pack.getUserLevel(game.user) ).|v10 – v14|Comprobación explícita<br>antes de iniciar la<br>escritura en BD.|



## 2. Modelo de Datos del Sistema D&D 5e ( Actor tipo "character" ) 

Un personaje jugable en dnd5e combina propiedades raíz del documento Foundry ( name , <mark>type: "character" ,</mark> img , prototypeToken , flags ) con el objeto system gobernado por su DataModel : 

- Identidad y narrativa: Actor.name , Actor.img , y bajo system.details se almacenan biografía ( biography.value ), apariencia ( appearance ), alineamiento ( alignment ), trasfondo ( background ), nivel ( level ) y experiencia ( xp ). 

- Especie/Raza, Clase y Subclase: En dnd5e moderno (2024: Species; 2014: Race), las clases, subclases, especies/razas y trasfondos se representan como Item s embebidos en el actor. El nivel total y el bonificador de competencia ( system.attributes.prof ) se derivan automáticamente a partir de los Item s de clase. 

- Atributos, modificadores y salvaciones: Bajo system.abilities con claves str , dex , con , int , wis , cha . Cada atributo almacena su puntuación base ( value ) y competencia en salvación 

- ( proficient ), mientras el sistema deriva mod y el total de salvación. 

- Habilidades y Percepción: Bajo system.skills indexadas por códigos de 3 letras ( acr , ani , 

- arc , ath , dec , his , ins , itm , inv , med , nat , prc , prf , per , rel , slt , ste , sur ), 

- con nivel de competencia ( 0 , 0.5 media, 1 competente, 2 pericia). La percepción pasiva deriva de prc . 

- Combate, HP, CA, Velocidad e Iniciativa: system.attributes.hp ( value , max , temp ), system.attributes.ac (derivada de armadura equipada/base), system.attributes.init y system.attributes.movement ( walk , fly , swim , climb , burrow , units ). 

- Rasgos defensivos, sentidos, idiomas y tamaño: Bajo system.traits se almacenan tamaño ( size : "sm" , "med" , "lg" ), resistencias ( dr ), inmunidades al daño ( di ), inmunidades a condición ( ci ), vulnerabilidades ( dv ), idiomas ( languages ) y competencias de 

- armas/armaduras/herramientas. Los sentidos se ubican en system.attributes.senses o system.traits.senses según la subversión. 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 3 of 13 

Condiciones, recursos y lanzamientos de conjuros: Las condiciones activas se representan como 

ActiveEffect s embebidos. Los espacios de conjuro residen en system.spells 

( spell1 .. spell9 , pact ) y el atributo de lanzamiento en system.attributes.spellcasting . En 

versiones con Activities, los recursos de clase suelen vincularse a los propios Item s de clase/rasgo. 

Inspección en vivo del esquema exacto en tu Foundry: Ejecuta en la consola del navegador ( F12 ) dentro de un mundo dnd5e : console.log(CONFIG.Actor.dataModels.character.schema.fields); y console.log(Object.keys(CONFIG.Item.dataModels)); 

## 3. Representación de Items Embebidos en dnd5e 

Todos los objetos, equipo, conjuros y rasgos de un personaje son documentos Item embebidos en actor.items . En dnd5e moderno, las acciones (ataque, daño, salvación, curación, consumo) se estructuran mediante Activities dentro del Item : 

- Armas ( type: "weapon" ): Almacenan name , img , categoría marcial/sencilla, propiedades ( fin , lgt , hvy , two , ver , thr , amm , lod ), peso ( weight ), precio ( price ), estado equipped , 

- descripción ( description.value ) y Activities con fórmula de daño, tipo de daño, alcance y munición. 

- Armaduras y Escudos ( type: "equipment" ): Almacenan clase de armadura base, tipo (ligera, media, pesada, escudo), requisito de Fuerza, desventaja en Sigilo, equipped , peso, precio y descripción. 

- Objetos, Consumibles y Herramientas ( "loot" , "consumable" , "tool" , "container" ): Almacenan cantidad ( quantity ), peso, precio, usos/cargas y descripción. 

- Hechizos ( type: "spell" ): Almacenan nivel ( level , 0 para trucos), escuela ( school ), tiempo de lanzamiento ( activation ), alcance ( range ), componentes/materiales, duración ( duration ), concentración, ritual, preparación y Activities de ataque/salvación/daño. 

- Features, Clases, Subclases, Especies y Trasfondos: Se almacenan también como Item s embebidos con sus respectivos tipos ( class , subclass , race /especie, background , feat ), agrupando rasgos raciales, rasgos de clase/subclase, dotes (feats) y rasgos de trasfondo. 

## 4. Niveles de Prioridad de Datos de Exportación 

|Nivel|Datos Requeridos desde RolForge|
|---|---|
|Nivel 1 —|Metadatos de formato/versión,<br>name ,<br>level , al menos una clase principal|
|Imprescindible<br>(Reconstrucción mínima)|(nombre y nivel), puntuaciones base de los 6 atributos (<br>str –<br>cha ),<br>hp.maxy<br>hp.value , y equipo/rasgos esenciales.|
|Nivel 2 — Importante<br>(Fidelidad completa)|Especie/raza, subclase y trasfondo (nombre + UUID opcional), salvaciones y<br>habilidades con nivel de competencia/pericia, competencias de<br>armas/armaduras/herramientas, idiomas, velocidades, tamaño, sentidos,<br>hp.temp ,<br>dados de golpe, XP, alineamiento, inspiración, inventario completo, hechizos y rasgos<br>con descripciones y usos.|
|Nivel 3 — Opcional|Retrato (<br>img ), configuración de<br>PrototypeToken ,|
|(Ampliación posterior)|resistencias/inmunidades/vulnerabilidades, condiciones activas, biografía extensa,<br>monedas detalladas y<br>ActiveEffect s personalizados.|



RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 4 of 13 

## 5. Tabla de Mapeo RolForge → Foundry ( dnd5e ) 

|RolForge (JSON)|Foundry D&D 5e|Tipo|Oblig.|
|---|---|---|---|
|character.name|Actor.name|string|Sí|
|character.img|Actor.img|string|No|
|character.level|Actor.system.details.levely<br>Itemde<br>clase|number|Sí|
|character.xp|Actor.system.details.xp (verificar<br>schema)|number/ob<br>j|No|
|character.alignment|Actor.system.details.alignment|string|No|
|character.inspiration|Actor.system.attributes.inspiration|boolean|No|
|character.proficiencyBon<br>us|Derivado en<br>Actor.system.attributes.prof|number|Calc.|
|character.abilities.<br>{str..cha}|Actor.system.abilities.<br>{str..cha}.value|number|Sí|
|character.abilityModifie<br>rs.*|Derivado en<br>Actor.system.abilities.<br><ab>.mod|number|Calc.|
|character.savingThrows.<br>*|Actor.system.abilities.<br><ab>.proficient|number/bo<br>ol|No|
|character.skills.<br><key>.proficiency|Actor.system.skills.<key>.value(0,<br>0.5, 1, 2)|number|No|
|character.perception|Derivado de<br>Actor.system.skills.prc|number|Calc.|
|character.initiative|Actor.system.attributes.init|object/nu<br>m|No|
|character.movement.*|Actor.system.attributes.movement.<br>{walk,fly,swim,climb,burrow,units}|number/st<br>r|No|
|character.hp.max / value<br>/ temp|Actor.system.attributes.hp.<br>{max,value,temp}|number|Sí<br>(max/val)|
|character.hitDice|Item(<br>type: "class" ) y/o<br>system.attributes.hp|object/st<br>r|No|
|character.ac|Calculado en<br>Actor.system.attributes.ac|object/nu<br>m|Calc.|
|character.size|Actor.system.traits.size|string|No|
|character.senses|Actor.system.attributes.senses/<br>traits.senses|object|No|
|character.resistances /<br>immunities /<br>vulnerabilities|Actor.system.traits.{dr,di,dv}|object/ar<br>r|No|
|character.conditions|ActiveEffect s embebidos en el<br>Actor|ActiveEff<br>ect[]|No|



RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 5 of 13 

|RolForge (JSON)|Foundry D&D 5e|Tipo|Oblig.|
|---|---|---|---|
|character.languages /<br>proficiencies|Actor.system.traits.<br>{languages,weaponProf,armorProf,toolP<br>rof}|object|No|
|character.spellcasting /<br>spellSlots|system.attributes.spellcastingy<br>system.spells.spell1..9|string/ob<br>j|No|
|character.species /<br>background|Item s embebidos (especie/raza y trasfondo)|Item|No|
|character.classes[]|Item s embebidos<br>type: "class"y<br>"subclass"|Item[]|Sí (≥1)|
|character.items[]|Item s embebidos (<br>weapon ,<br>equipment ,<br>spell ,<br>feat , etc.)|Item[]|No|



## 6. Especificación del Formato JSON de Exportación de RolForge 

El formato incluye identificación explícita, versionado de esquema ( schemaVersion ), metadatos de origen, sistema destino y contenedor extensible ( extensions ) para evolucionar sin romper compatibilidad: 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 6 of 13 

{ "format": "rolforge-character", "schemaVersion": 1, "exportedAt": "2026-11-23T08:00:00.000Z", "source": { "app": "RolForge", "appVersion": "1.4.0", "characterId": "rf_char_98a7bc12" }, "target": { "system": "dnd5e", "ruleset": "2024" }, "character": { "name": "Lyra Shadowstep", "img": null, "level": 5, "xp": 6500, "alignment": "Chaotic Good", "inspiration": false, "size": "med", "species": { "name": "Elf", "foundryUuid": null }, "background": { "name": "Criminal", "foundryUuid": null }, "classes": [ { "name": "Rogue", "levels": 5, "subclass": { "name": "Arcane Trickster", "foundryUuid": null }, "foundryUuid": null } ], "abilities": { "str": 10, "dex": 18, "con": 14, "int": 14, "wis": 12, "cha": 10 }, "savingThrows": { "str": false, "dex": true, "con": false, "int": true, "wis": false, "cha": false }, "skills": { "ste": { "proficiency": 2 }, "prc": { "proficiency": 1 }, "acr": { "proficiency": 1 } }, "hp": { "max": 38, "value": 38, "temp": 0, "hitDiceFormula": "5d8" }, "movement": { "walk": 30, "fly": 0, "swim": 0, "climb": 0, "burrow": 0, "units": "ft" }, "senses": { "darkvision": 60, "units": "ft" }, "languages": ["Common", "Elvish", "Thieves' Cant"], "proficiencies": { "weapons": ["Simple Weapons", "Rapiers"], "armor": ["Light Armor"], "tools": ["Thieves' Tools"] }, "traits": { "resistances": [], "immunities": [], "vulnerabilities": [], "conditions": [] }, "spellcasting": { "ability": "int", "slots": { "spell1": { "value": 4, "max": 4 }, "spell2": { "value": 2, "max": 2 } } }, "items": [ { "kind": "weapon", "name": "Rapier", "foundryUuid": null, "quantity": 1, "equipped": true, "weight": 2, "price": { "value": 25, "denomination": "gp" }, "description": "A slender, pointed blade.", "weapon": { "category": "martialM", "properties": ["fin"], "damage": { "dice": "1d8", "type": "piercing" } } }, { "kind": "spell", "name": "Mage Hand", "foundryUuid": null, "level": 0, "school": "conjuration", "description": "..." }, { "kind": "feature", "origin": "class", "sourceName": "Rogue", "name": "Sneak Attack", "description": "..." } ], "notes": { "biography": "", "appearance": "", "backstory": "" }, "extensions": {} } } 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 7 of 13 

Validación y retrocompatibilidad: El módulo ejecuta funciones migradoras secuenciales 

( migrateV1ToV2 ) si schemaVersion es anterior a la actual, rechaza versiones futuras no soportadas y valida en dos capas: estructura/tipos/rangos ( 1..30 en atributos, 1..20 en nivel, hp.max >= 1 ) y entorno (sistema dnd5e activo y permisos de usuario). 

## 7. Estructura de Carpetas del Módulo ( rolforge-importer ) 

|rolf|orge-importer/||
|---|---|---|
|├── <br>["dn|module.json<br>d5e"], esmodules)|# Manifiesto oficial (id, compatibilidad v13-v14, system:|
|├──|lang/||
|│|├──en.json|# Localización en inglés|
|│ <br>├──|└──es.json<br>scripts/|# Localización en español|
|│ <br>│ <br>estr|├──main.js<br> ├──validator.js<br>icta|# Hooks init/ready y renderActorDirectory para lanzar la UI<br># Lectura de archivo, migración de schemaVersion y validación|
|│|├──compendium.js|# Búsqueda por UUID (fromUuid) o índice de compendios|
|(get|<br>Index)||
|│|├──mapper.js|# Conversión del contrato RolForge al modelo Actor e Items de|
|dnd5|e||
|│|├──importer.js|# Orquestación de Actor.create(), createEmbeddedDocuments() y|
|flag|<br>s||
|│ <br>├──|└──ui.js<br>templates/|# Ventana ApplicationV2 + confirmaciones DialogV2|
|│ <br>└──|└──importer.hbs<br>styles/|# Plantilla Handlebars para selección, resumen y errores|
||└──importer.css|# Estilos encapsulados bajo la clase .rolforge-importer|



## 8. Flujo de Interfaz ( ApplicationV2 + DialogV2 ) 

1. Disparador: Botón "RolForge JSON" en la cabecera del Directorio de Actores. 

2. Selección de archivo: Input local <input type="file" accept=".json,application/json"> (o foundry.applications.apps.FilePicker para archivos en servidor). 

3. Validación inmediata: Al seleccionar el archivo, se parsea y valida el JSON antes de habilitar la creación. 

4. Vista previa del resumen: Muestra nombre, especie/raza, clase(s), nivel, HP máximo, número de ítems/hechizos y advertencias no bloqueantes. 

5. Confirmación y creación: Confirmación mediante DialogV2.confirm(...) , ejecución de importCharacterToFoundry() , apertura automática de la hoja ( actor.sheet.render(true) ) y 

reporte claro de errores si algo falla. 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 8 of 13 

## 9. Ejemplos de Código de Referencia (Foundry v14) 

### A. Registro y botón en el Directorio de Actores ( scripts/main.js ) 

import { RolForgeImporterApp } from "./ui.js"; const MODULE_ID = "rolforge-importer"; Hooks.once("ready", () => { if (game.system.id !== "dnd5e") return; game.modules.get(MODULE_ID).api = { openImporter: () => new RolForgeImporterApp().render(true) }; }); Hooks.on("renderActorDirectory", (_app, html) => { if (html.querySelector(".rolforge-import-btn")) return; const headerActions = html.querySelector(".directory-header .header-actions"); if (!headerActions) return; const btn = document.createElement("button"); btn.type = "button"; btn.className = "rolforge-import-btn"; btn.innerHTML = `<i class="fa-solid fa-file-import"></i> RolForge JSON`; btn.addEventListener("click", () => new RolForgeImporterApp().render(true)); headerActions.appendChild(btn); }); 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 9 of 13 

### B. Validación del JSON ( scripts/validator.js ) 

const VALID_ABILITIES = ["str", "dex", "con", "int", "wis", "cha"]; const SUPPORTED_VERSIONS = [1]; 

export async function readJsonFile(file) { if (!(file instanceof File)) throw new Error("Archivo no válido."); if (file.size > 5 * 1024 * 1024) throw new Error("El archivo supera el límite de 5 MB."); return JSON.parse(await file.text()); } 

export function validateRolForgePayload(payload) { const errors = []; 

if (!payload || typeof payload !== "object" || Array.isArray(payload)) { return { valid: false, errors: ["La raíz del JSON debe ser un objeto."], normalized: null }; } if (payload.format !== "rolforge-character") errors.push("Formato no reconocido."); if (!SUPPORTED_VERSIONS.includes(payload.schemaVersion)) errors.push("Versión de esquema no soportada."); 

if (payload.target?.system && payload.target.system !== "dnd5e") errors.push("El sistema destino debe ser dnd5e."); 

const c = payload.character; 

if (!c || typeof c.name !== "string" || !c.name.trim()) errors.push("Falta el nombre del personaje."); if (!Number.isInteger(c?.level) || c.level < 1 || c.level > 20) errors.push("Nivel fuera de rango (1–20)."); 

for (const ab of VALID_ABILITIES) { const val = c?.abilities?.[ab]; 

if (typeof val !== "number" || Number.isNaN(val) || val < 1 || val > 30) errors.push(`Atributo '${ab}' inválido.`); } 

if (typeof c?.hp?.max !== "number" || c.hp.max < 1) errors.push("HP máximo inválido."); 

return { valid: errors.length === 0, errors, normalized: errors.length === 0 ? payload : null }; 

} 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 10 of 13 

C. Resolución en Compendios y Creación de Actor + Items ( scripts/compendium.js e importer.js ) 

export async function resolveItemFromCompendiums({ name, foundryUuid, itemType }) { if (foundryUuid) { const doc = await fromUuid(foundryUuid).catch(() => null); if (doc?.documentName === "Item") return doc.toObject(); } if (!name) return null; const target = name.trim().toLowerCase(); for (const pack of game.packs) { if (pack.documentName !== "Item") continue; if (pack.getUserLevel(game.user) === CONST.DOCUMENT_OWNERSHIP_LEVELS.NONE) continue; const index = await pack.getIndex({ fields: ["name", "type"] }); const hit = index.find((e) => e.name?.trim().toLowerCase() === target && (!itemType || e.type === itemType)); if (hit) { const doc = await pack.getDocument(hit._id); if (doc) return doc.toObject(); } } return null; } export async function importCharacterToFoundry(payload) { if (!game.user.can("ACTOR_CREATE")) throw new Error("Sin permisos para crear Actores en este mundo."); const c = payload.character; const warnings = []; const actor = await Actor.create({ name: c.name.trim(), type: "character", img: c.img || "icons/svg/mystery-man.svg", system: { abilities: Object.fromEntries(["str","dex","con","int","wis","cha"].map((k) => [k, { value: c.abilities[k] }])), attributes: { hp: { value: c.hp.value ?? c.hp.max, max: c.hp.max, temp: c.hp.temp ?? 0 }, movement: { walk: c.movement?.walk ?? 30, units: c.movement?.units ?? "ft" } } }, flags: { "rolforge-importer": { schemaVersion: payload.schemaVersion, importedAt: new Date().toISOString() } } }); const embedded = []; for (const item of c.items ?? []) { const resolved = await resolveItemFromCompendiums({ name: item.name, foundryUuid: item.foundryUuid }); if (resolved) { delete resolved._id; embedded.push(resolved); } else { warnings.push(`Ítem '${item.name}' no encontrado en compendios; creado como elemento básico.`); embedded.push({ name: item.name, type: item.kind === "spell" ? "spell" : item.kind === "weapon" ? "weapon" : "loot", system: { description: { value: item.description ? ~~`<p>${foundry utils escapeHTML(item description)}</p>` : "" } }~~ 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 11 of 13 

## 10. Versiones, Compatibilidad 2014/2024 y Compendios 

- Versiones objetivo: Foundry VTT v14 (mínimo recomendado v13 en module.json ) y dnd5e v6.x. Evita APIs obsoletas como Application / Dialog v1, FilePicker global sin namespace y helpers internos no documentados de dnd5e . 

- Reglas 2014 vs. 2024: En 2024 se usa Species (en lugar de Race) y los aumentos de atributo provienen principalmente de Backgrounds y Origin Feats. Exportar solo texto plano como "Paladin" o "Elf" no aporta rasgos por nivel ni Advancements; por ello el módulo busca primero el documento equivalente en compendios ( game.packs ) y recurre a ítems básicos únicamente como respaldo (fallback). 

- Diferencias de idioma y duplicados: Normaliza nombres (minúsculas, sin acentos), prioriza compendios según target.ruleset ( "2014" o "2024" ) y registra advertencias no bloqueantes cuando un ítem homebrew no exista en ningún compendio. 

## 11. Gestión de Errores, Seguridad y Separación TXT vs. JSON 

- Catálogo de errores comprensibles: Diferencia fallos bloqueantes (JSON malformado, 

- schemaVersion incompatible, sistema distinto de dnd5e , campos obligatorios ausentes, atributos 

- fuera de rango 1..30 , falta de permiso ACTOR_CREATE ) de advertencias no bloqueantes (clase o ítem no hallado en compendios). 

- Seguridad frente a JSON no confiable: Limita el tamaño máximo del archivo, valida tipos y rangos numéricos estrictos, escapa cualquier cadena de texto antes de insertarla como HTML mediante foundry.utils.escapeHTML() para prevenir XSS, bloquea claves peligrosas ( __proto__ , constructor , prototype ) y nunca evalúa expresiones dinámicas provenientes del JSON. 

- Exportación dual en RolForge: Mantén separados Exportar TXT (lectura humana e impresión) y Exportar JSON para Foundry (contrato tipado y versionado), evitando parsear texto libre propenso a errores de formato. 

## 12. Plan de Implementación por Fases y Checklist Final 

|Fase|Entregables|Criterio de Prueba|
|---|---|---|
|Fase 1: Esqueleto|module.json ,<br>main.js ,<br>importer.css ,<br>lang/es.jsony<br>en.json .|Activar el módulo en Foundry v14<br>+<br>dnd5esin errores en consola.|
|Fase 2: Validador|Contrato JSON v1 en RolForge +<br>scripts/validator.js .|Validar JSONs correctos,<br>corruptos, versiones inválidas y<br>rangos extremos.|
|Fase 3: Actor Base|scripts/mapper.jsy creación de<br>Actor<br>(<br>character ) en<br>importer.js .|Comprobar atributos, HP,<br>velocidad y cálculos derivados en<br>la hoja<br>dnd5e .|
|Fase 4: Compendios<br>e Items|scripts/compendium.jsy<br>createEmbeddedDocuments("Item", ...) .|Importar clases, armas,<br>armaduras, hechizos y rasgos con<br>fallback.|
|Fase 5: UI y<br>Seguridad|ApplicationV2+<br>DialogV2(<br>ui.js ,<br>importer.hbs ) y escapado HTML.|Probar flujo completo como GM,<br>como jugador sin permisos y con<br>payloads XSS.|



RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 12 of 13 

|Fase|Entregables|Criterio de Prueba|
|---|---|---|
|Fase 6: Integración|Botón dual TXT/JSON en RolForge y pruebas<br>cruzadas 2014 / 2024.|Importar personajes de distintos<br>niveles y clases en Foundry v14 +<br>dnd5e6.x.|



Checklist previo antes de escribir la primera línea de código: (1) Confirmar versiones exactas de Foundry v14 y dnd5e v6.x en el entorno de pruebas; (2) inspeccionar en consola CONFIG.Actor.dataModels.character.schema y CONFIG.Item.dataModels ; (3) definir el orden de prioridad de compendios 2014/2024; (4) congelar el esquema JSON v1 entre RolForge y el módulo; (5) verificar permisos ACTOR_CREATE de los jugadores; (6) aplicar escapado HTML y validación estricta en todas las entradas. 

RolForge Importer — Especificación Técnica (Foundry VTT v14 / D&D 5e) 

Page 13 of 13 

