// attālums lietotājam saprotamā formā
export function formatDistance(km) {
  if (km === null || km === undefined) return ''
  return km < 1 ? '< 1 km' : `≈ ${Math.round(km)} km`
}
