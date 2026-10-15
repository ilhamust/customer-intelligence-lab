// src/plugins/global.js
import BaseButton from '@/components/BaseButton.vue'
import BaseInput from '@/components/BaseInput.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BaseCard from '@/components/BaseCard.vue'
import BaseEmpty from '@/components/BaseEmpty.vue'
import BaseLoading from '@/components/BaseLoading.vue'
import BaseTable from '@/components/BaseTable.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseStat from '@/components/BaseStat.vue'

export default {
  install(app) {
    app.component('BaseButton', BaseButton)
    app.component('BaseInput', BaseInput)
    app.component('BaseBadge', BaseBadge)
    app.component('BaseCard', BaseCard)
    app.component('BaseEmpty', BaseEmpty)
    app.component('BaseLoading', BaseLoading)
    app.component('BaseTable', BaseTable)
    app.component('BasePagination', BasePagination)
    app.component('BaseDialog', BaseDialog)
    app.component('BaseStat', BaseStat)
  },
}
