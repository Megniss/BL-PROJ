export function coverColor(book) {
  const colors = [
    'linear-gradient(135deg, #4f7c52, #2d4a30)',
    'linear-gradient(135deg, #7c5c4f, #4a3029)',
    'linear-gradient(135deg, #4f5d7c, #2a3050)',
    'linear-gradient(135deg, #7c4f6f, #4a2042)',
    'linear-gradient(135deg, #4f7a7c, #284a4c)',
    'linear-gradient(135deg, #7c7a4f, #4a4820)',
  ]
  return colors[book.id % colors.length]
}
