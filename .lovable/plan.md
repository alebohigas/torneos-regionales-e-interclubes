# Salidas normales por fecha, campo y categoría

## Resultado
- Mantener `tiposalida = 1` como salida única, con todas las categorías reunidas en “Grupos de Juego”.
- Para `tiposalida = 0`, mostrar primero la fecha y después una cuadrícula de categorías como la referencia.
- Cada categoría mostrará su propio campo, tee y cantidad de grupos.
- Mantener el buscador de jugadores disponible en la selección de categorías.
- Al elegir una categoría, mostrar únicamente sus grupos y jugadores.

## Implementación
- Ajustar la respuesta de Salidas para incluir `tipoSalida` y conservar el campo correspondiente a cada calendario/categoría, sin mezclar campos del mismo día.
- Añadir el nivel de selección de categoría en la página de Salidas normales.
- Corregir la navegación de regreso: detalle → categorías → días.
- Verificar compilación y la navegación con salida normal y salida única.

## Archivos principales
- `server/api/salidas.php`
- `server/api/salidas_det.php`
- `src/hooks/useSalidasData.ts`
- `src/pages/Salidas.tsx`
