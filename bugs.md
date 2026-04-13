# BUGS Y PENDIENTES

## GENERALES

- ~~En búsqueda no jala nuevos proveedores ni nuevos clientes.~~ ✅ (falta DECOLECTA_TOKEN en .env del servidor)
- ~~Usuarios: cuando está creado, al momento de editar no sale para cambiar contraseña.~~ ✅
- ~~Refrescar siempre cuando se haga una acción, y lo que se haga en una PC se muestre en tiempo real a las demás.~~ ✅ (Laravel Reverb WebSocket implementado)
- ~~Fecha emisión: formato hora/minuto/segundo AM y PM.~~ ✅
- ~~Formulario de fecha: filtrar siempre el hoy por defecto.~~ ✅
- ~~Una vez que se busque un producto y salga del flotante de búsqueda, se tiene que refrescar el nombre y el selector; que se mantenga en el buscador que está.~~ ✅

## ALMACEN

- Nombres de los precios: Precio Público, Precio Ferretería, Precio Especial, Precio Final - Ventas.

## COMPRAS

- En la búsqueda de productos, en detalles de precio no filtra bien las columnas a seleccionar para visualización.
- No me permite hacer compras al contado.
- Los egresos asociados no se visualizan.
- No limpia cuando pongo en espera.

## MIS COMPRAS

- Filtro de estado de cuenta, estado de compra y pendientes de recepción.
- Cuando recepciono, el cliente sale como 19; no busca nuevamente con el nombre que está guardado.
- Cuando pongo defrente finalizar recepción no se muestra en mis recepciones.

## VENTAS

- ~~No me abre el buscador de clientes cuando no escribo nada; debería salir sin filtrar nada.~~ ✅
- ~~En clientes no funciona el calendario en cumpleaños.~~ ✅

## MIS VENTAS

- ~~No se aprecia bien los filtros, está mezclado, y cuando pago un crédito no cambia de color.~~ ✅ (filtros reorganizados en 2 filas uniformes + invalidación de QueryKeys.VENTAS al registrar cobro)

## ORDEN DE COMPRA

- Fecha filtre hoy y tipo documento default en Factura y forma de pago en Contado; ahora serie, número, guía, percepción y egreso asociado no van.

## MIS ORDENES DE COMPRA

- Consultar cómo funciona los filtros de estado.
- ~~Duplicar: no jala bien los datos.~~ ✅ (RUC mostraba ID del proveedor en vez del número de documento, corregido con proveedorOptionsDefault + initialSearchText)

## APERTURA DE CAJA

- Sigue habiendo el bug: cuando creo cajas, automático se crea una caja de apertura.

## FACTURACION

- De este módulo cuando agrego un gasto está mal o no jala el mismo modal de gastos de contabilidad y no se registra.

## MIS ENTREGAS

- ~~Cuando voy a despachar y selecciono en el mapa, no jala la del GPS sino de la dirección.~~ ✅ (fallback a coordenadas GPS del cliente cuando la entrega no tiene coordenadas propias)

## WHATSAPP / CORREO

- Todo documento emitido se envíe por WhatsApp (enviar debe enviar el link para descargar ese documento; si doy enviar y descargar, que se envíe con su link y que se descargue el documento para enviarlo también) y correo del ejemplo que les mandé.
- Cuando cierro y vuelvo a abrir ya no se puede abrir para enviar por WhatsApp.
