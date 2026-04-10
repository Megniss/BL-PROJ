const map = { New: 'tag-green', Good: 'tag-blue', Fair: 'tag-orange', Worn: 'tag-red' }

export function conditionClass(condition) {
  return map[condition] || ''
}
