# ALEIN SYSTEM — tarjetas para impresión

## Objetivo
Agregar en Administración una pestaña **ALEIN SYSTEM** con dos accesos:
- **Tarjetas para impresión**: funcional en esta primera entrega.
- **Salidas para impresión**: visible como siguiente opción, sin desarrollar todavía su formato.

Todo se abrirá dentro del mismo sitio, sin crear un subdominio real.

## Experiencia administrativa
- Añadir la pestaña **ALEIN SYSTEM** al panel principal de Admin.
- Mostrar dos opciones claras mediante tarjetas de navegación.
- Abrir **Tarjetas para impresión** en una página independiente y limpia, protegida por el acceso administrativo existente.
- Incorporar filtros por fecha, campo, categoría y sistema, conservándolos también en la dirección de la página para poder repetir o compartir una selección.
- Incluir acciones de previsualización e impresión.

## Formato de cada tarjeta
Reproducir la estructura visual de la referencia, adaptada a los datos del torneo activo:

1. Encabezado del torneo con logotipo, nombre, sede y fecha.
2. Primer recuadro:
   - Hoyo y hora.
   - Nombre del jugador y club.
   - Categoría y tee de salida.
3. Segundo recuadro con 18 hoyos, V1, V2 y total:
   - HOYO.
   - YARDAS.
   - PAR TIME.
   - SCORE GROSS.
4. No mostrar filas de **Ventaja**, **Hándicap** ni **Score Neto**.
5. La fila **SCORE GROSS** tendrá 1.5 veces la altura de las demás filas.
6. Pie de firmas con Jugador, Anotador, Sistema y Folio.

## Impresión
- Hoja tamaño carta en orientación vertical.
- Exactamente tres tarjetas uniformes por hoja.
- Saltos de página automáticos después de cada tercera tarjeta.
- Márgenes y tamaños fijos para evitar recortes, desbordamientos o cambios entre navegadores.
- Ocultar filtros, navegación y botones al imprimir.
- Mantener líneas, textos y logotipo nítidos en blanco y negro o color.

## Datos
- Crear un endpoint de solo lectura para devolver, según fecha/campo/categoría/sistema, el torneo y las tarjetas de todos los jugadores seleccionados.
- Consultar el esquema legacy de `golftour` con detección de nombres de columnas, como los endpoints actuales.
- Obtener grupos, hoyo de salida, hora, jugador, club, categoría, tee, yardajes, par, horarios por hoyo, sistema y folio desde las tablas/vistas existentes.
- Mantener `torneoid` en todas las solicitudes y usar el proxy existente para el logotipo.
- Si algún dato opcional no existe, dejar su espacio vacío sin impedir la impresión.

## Integración técnica
- Registrar las nuevas rutas administrativas y el módulo en el catálogo modular.
- Añadir constructores de URL y tipos del lado de la página.
- Registrar en `AGENTS.md` que ALEIN SYSTEM es el área administrativa de formatos imprimibles y que sus vistas de impresión no usan el Layout público.
- No usar la imagen subida como contenido: se utiliza únicamente como referencia visual.

## Verificación
- Comprobar la selección de filtros y la carga de varias tarjetas.
- Revisar visualmente una hoja carta a 1280 px y una vista móvil de administración.
- Generar una impresión/PDF de prueba y confirmar tres tarjetas por página, sin elementos cortados ni superpuestos.
- Confirmar que la fila SCORE GROSS mide 1.5 veces las demás y que no aparecen Ventaja, Hándicap ni Neto.
- Revisar errores de compilación y ejecución antes de terminar.
