import type { TileLayerOptions } from 'leaflet';

/**
 * OpenStreetMap tiles, shared by every map. The tile usage policy requires a
 * Referer: the site sends `Referrer-Policy: same-origin`, which strips it, and
 * OSM answers such requests with 403 "Access blocked". The tile layer sends
 * the origin only (never the page path, so no resident IDs leave the LAN).
 */
export const osmTiles = {
    url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution:
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
    referrerPolicy: 'strict-origin-when-cross-origin',
} satisfies TileLayerOptions & { url: string };
