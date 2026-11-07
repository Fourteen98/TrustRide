import type { MapProps } from './trip-map';
import { WebPage } from './web-page';

export type { MapProps, MapStop } from './trip-map';

/**
 * The web build has no native map, so it shows Aveo's own map page, which draws the same things.
 * Map only: the screens around it already show the stops and how far away the van is.
 */
export function TripMap({ mapUrl }: MapProps) {
  return mapUrl ? <WebPage url={`${mapUrl}${mapUrl.includes('?') ? '&' : '?'}embed=1&compact=1`} title="Map of your van" /> : null;
}
