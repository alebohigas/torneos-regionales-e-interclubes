---
name: Modelo por giras (BD golftour)
description: El sitio se basa en gira.giraid (no torneoid); copas.grupocopas consolida copas de la misma gira; uso=0 => COPA TERMINADA
type: feature
---

En la BD `golftour` la página se organiza por **giras**:

- `gira(giraid PK, nombre, uso)` — `uso = 1` operativa; `uso = 0` => se muestra el nombre
  con el aviso "COPA TERMINADA" en público y una alerta en `/admin`.
- `copas(copasid PK, giraid FK, nombre, grupocopas)` — `grupocopas` es CSV de `copasid`
  cuya información consolida esa copa (copa general). **Nunca puede incluir copasid de
  otra gira**; el endpoint filtra ids inválidos. Vacío => usa solo su propio copasid.
- `torneo.giraid` liga los torneos a la gira.

Configuración: `site_config.giraid` (migración `2026_08_21_add_giraid_to_site_config.sql`),
editable en `/admin` → Config (componente `AdminGira`). `torneoid` se conserva para módulos
que aún requieren un torneo puntual.

Endpoint: `api/gira.php` (sin params = lista de giras; `?giraid=` = detalle con copas y torneos).
Frontend: `useGiraId` (localStorage + evento, igual que `useTorneoId`) y `useGiraData`
(`useGiraInfo`, `useGirasList`). El hero de `/home` muestra el nombre de la gira.
