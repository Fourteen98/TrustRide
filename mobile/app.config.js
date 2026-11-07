/**
 * Adds what app.json can't hold because it comes from the environment.
 *
 * GOOGLE_MAPS_ANDROID_KEY: a Google Maps Platform key with "Maps SDK for Android" switched on,
 * restricted in Google Cloud to this app's package name. Only store and development builds for
 * Android need it. Expo Go and the iPhone (Apple Maps) don't.
 */
module.exports = ({ config }) => ({
  ...config,
  android: {
    ...config.android,
    config: {
      ...config.android?.config,
      googleMaps: { apiKey: process.env.GOOGLE_MAPS_ANDROID_KEY ?? '' },
    },
  },
});
