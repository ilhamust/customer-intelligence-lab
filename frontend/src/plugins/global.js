// src/plugins/global.js
import BaseButton from '@/components/BaseButton.vue'
import BaseInput from '@/components/BaseInput.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BaseCard from '@/components/BaseCard.vue'
import BaseEmpty from '@/components/BaseEmpty.vue'
import BaseLoading from '@/components/BaseLoading.vue'

export default {
  install(app) {
    app.component('BaseButton', BaseButton)
    app.component('BaseInput', BaseInput)
    app.component('BaseBadge', BaseBadge)
    app.component('BaseCard', BaseCard)
    app.component('BaseEmpty', BaseEmpty)
    app.component('BaseLoading', BaseLoading)
  },
}
