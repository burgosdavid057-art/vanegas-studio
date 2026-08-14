# Vanegas Studio — ERP financiero mensual

Panel financiero mensual hecho a la medida de un fotógrafo/videógrafo. Los
movimientos son la única fuente de verdad: de ahí salen los reportes, los
saldos por cliente y los indicadores del mes.

## Módulos

- **Clientes mensuales** — matriz de mensualidades con estado por cliente
- **Trabajos foto/video** — proyectos con costos y pagos parciales
- **Finanzas** — ingresos, egresos y categorías
- **Reportes** — cierres mensuales y comparativas

## Stack

- PHP puro (sin framework)
- SQLite como base de datos
- Tailwind CSS

## Desarrollo local

```bash
php -S localhost:8000
```

Requiere PHP 8+ y la extensión SQLite habilitada.

## Deploy

Desplegado en [davidburgos.dev/vanegas](https://davidburgos.dev/vanegas) sobre
un Plesk (misma cuenta de davidburgos.dev). Suscripción aparte de otros
proyectos de la cuenta.

## Estado

En producción. Uso interno del cliente.
