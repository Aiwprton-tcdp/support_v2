
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

export const FormatDateTime = data => {
  if (data == null) data = new Date()

  let tyu = new Date(new Date(data).toDateString()) < new Date(new Date().toDateString())
  const options = tyu ? {
    month: '2-digit',
    year: '2-digit',
    day: '2-digit',
    hour: 'numeric',
    minute: 'numeric',
  } : {
    hour: 'numeric',
    minute: 'numeric',
  }

  return new Date(data).toLocaleTimeString('az-Cyrl-AZ', options)
}
