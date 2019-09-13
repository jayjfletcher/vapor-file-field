Nova.booting((Vue, router, store) => {
    Vue.component('index-vapor-file-field', require('./components/IndexField'))
    Vue.component('detail-vapor-file-field', require('./components/DetailField'))
    Vue.component('form-vapor-file-field', require('./components/FormField'))
})
