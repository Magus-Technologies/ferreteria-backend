# Bugs y Pendientes

## General

- [x] Implementar consulta con nuevo proveedor de RUC/DNI
- [x] En la búsqueda de productos, en Detalle de Precios no filtra bien las columnas a seleccionar para visualización
- [x] Que las franjas de color sean más claras al seleccionar
- [x] Fecha de emisión: formato con hora/minuto/segundo AM/PM
- [x] Formulario de fecha: filtrar siempre el "hoy" por defecto
- [ ] Una vez que se busque un producto y salga del flotante de búsqueda, refrescar el nombre y el selector para que se mantenga en el buscador actual
- [ ] Refrescar automáticamente cuando se haga cualquier acción
- [ ] Ver tema de usuarios: cambio de contraseña y creación
- [ ] Agregar en la campana notificaciones de crédito de ventas y compras
- [ ] Agregar tabs de "Solicitud de Órdenes de Compra" y tabla de "Requerimiento de Servicios"

## Almacén

- [ ] Nombres de los precios: Precio Público, Precio Ferretería, Precio Especial, Precio Final (alinear con Ventas)

## Compras y Ventas

- [ ] La búsqueda no está bien: al poner un número de RUC lo encuentra pero lo borra — revisar autocompletados

## Mis Compras

- [ ] Ver tema de filtros y los estados
- [ ] No limpia cuando pongo "En Espera"
- [ ] Egreso asociado: eliminar de donde no corresponde
- [ ] Filtro de Estado de Cuenta, Estado de Compra y Pendientes de Recepción
- [ ] Cuando pongo directo "Finalizar Recepción" no se muestra en Mis Recepciones
- [ ] Cuando recepciono, debería estar por defecto el cliente

## Órdenes de Compra

- [ ] Fecha filtre "hoy", tipo de documento en Factura y forma de pago en Contado. Serie y número, guía, percepción y egreso asociado no van
- [ ] Implementar impresión
- [ ] Consultar cómo funcionan los filtros de estado
- [ ] Duplicar (replicar la lógica de "duplicar productos" de Mi Almacén)

## Ventas

- [x] En las tablas no deja seleccionar bien los tipos de precio — debería refrescar al toque
- [ ] Lo que se selecciona del mapa es "en referencia": mostrarlo en gris y permitir agregar una referencia adicional. Lo seleccionado del mapa solo debe usarse para la navegación en Maps
- [ ] Despacho en Tienda y Despacho a Domicilio: descontar siempre al momento de vender. Despacho Parcial: descontar al seleccionar tipo de filtro (D. en Tienda y Despacho a Domicilio). Poner "Omitir" o que no sea obligatoria la selección del Despacho Parcial
- [ ] En venta con Despacho Parcial, agregar una tabla de "Despacho en Movilidad" con lo que se va a despachar
- [ ] En Mis Ventas, al seleccionar "Entrega" mostrar los 2 tipos de despacho (en Tienda o a Domicilio)

## Mis Ventas

- [ ] Una vez que la unidad termina su reparto, que el estado salga como "Completado"

## Kardex

- [ ] Buscador en tiempo real
- [ ] Corregir datos jalados de Kardex vs Inventario y Facturación
- [ ] Mostrar Stock Actual, Stock Anterior y Cuánto Ingresó

## Finalidades / Integraciones

- [ ] Todo documento emitido se envíe por WhatsApp y correo (según el ejemplo enviado)
