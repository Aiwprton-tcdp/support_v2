
export const StringVal = (data, min = 3, max = 150) => {
  const length = data.length
  let validation = Boolean()
  let correction = String()

  if (length < min) {
    validation = true
    correction = `короткое (${length} < ${min}-)`
  } else if (length > max) {
    validation = true
    correction = `длинное (${length} > ${max}+)`
  }

  if (validation) {
    this.toast(`Ошибка валидации: название слишком ${correction}`, 'error')
  }
  return validation
}
