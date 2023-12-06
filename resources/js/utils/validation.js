
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

export const FormatLinks = (data, hide_links = false) => {
  const pattern = /(https?:\/\/(?:[A-zА-яЁё0-9 ._-]+\/)+(?:\?id=\d+)?(?:[^.:]+\.[A-z]{1,5})?)/g
  const replacement = `<a href="$1" target="_blank">${hide_links ? 'Ссылка' : '$1' }</a>   `
  return data.replaceAll(pattern, replacement)
}

export const FormatDateTime = data => {
  if (data == null || data == "Invalid Date") data = new Date()

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
