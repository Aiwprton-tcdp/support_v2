import { Centrifuge } from 'centrifuge'

export const SocketStart = (axios, ticket_id) => {
  console.log(axios.defaults.baseURL)

  const centrifuge = new Centrifuge("wss://aiwprton.sms19.ru:3089/connection/websocket", {
    debug: true,
    subscribeEndpoint: axios.defaults.baseURL + "websocket/subscribe",
    onRefresh: (ctx, cb) => {
      let promise = fetch(axios.defaults.baseURL + "websocket/refresh", {
        method: "POST",
        user_id: this.currentUserID,
      }).then(resp => {
        resp.json().then(data => {
          localStorage.setItem('support_socket', data.token)
          cb(data.token)
          centrifuge.setToken(data.token)
          centrifuge.connect()
        })
      })
    },
  })
  centrifuge.setToken(localStorage.getItem('support_socket'))

  centrifuge.on('disconnect', function (context) {
    console.log("disconnected")
    console.log(context)
  })

  centrifuge.on('connect', function (context) {
    console.log("connected")
    console.log(context)
  })

  const sub = centrifuge.newSubscription("#support." + ticket_id)

  sub.on('publication', function (ctx) {
    console.log(ctx)
    console.log(ctx.data)
  })

  sub.subscribe()


  centrifuge.connect()
}
