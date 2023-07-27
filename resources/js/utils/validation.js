
export const StringVal = (data, min = 3, max = 150) => {
  const length = data.length
  let status = Boolean()
  let correction = String()

  if (length < min) {
    status = true
    correction = `короткое (${length} < ${min})`
  } else if (length > max) {
    status = true
    correction = `длинное (${length} > ${max})`
  }

  const message = `Ошибка валидации: название слишком ${correction}`

  return { status, message }
}

export const FormatLinks = data => {
  const pattern = /(https?:\/\/[А-яA-z0-9 ._-]+\/([A-z0-9 ._-]+\/)+)/g
  const replacement = '<a href="$1" target="_blank">$1</a>'
  return data.replaceAll(pattern, replacement)
}
