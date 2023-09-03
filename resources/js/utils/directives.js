
export const focus = {
  mounted: el => {
    let input = findInput(el, 10)

    if (input) {
      input.focus()
    }
  },
  updated: el => {
    let input = findInput(el, 10)

    if (input) {
      input.focus()
    }
  },
  // mounted: (el, active) => {
  //   // console.log(el)
  //   // console.log(active.value)
  //   if (!active.value) return
  //   let input = findInput(el, 10)

  //   if (input) {
  //     input.focus()
  //   }
  // },
  // updated: (el, active) => {
  //   // console.log(el)
  //   // console.log(active.value)
  //   if (!active.value) return
  //   let input = findInput(el, 10)

  //   if (input) {
  //     input.focus()
  //   }
  // },
  // unmounted: el => el.focus(),
}

const findInput = (el, max_depth = 1) => {
  if (['INPUT', 'TEXTAREA'].includes(el.nodeName)) return el
  else if (max_depth === 0) return null

  // Our current element is not an input, so we need to loop
  // over its children and call findInput recursively
  for (let child of el.children) {
    let input = findInput(child, --max_depth)

    // We've found our input, return it to unwind the stack
    // otherwise, continue through the loop
    if (input) return input
  }

  return null
}