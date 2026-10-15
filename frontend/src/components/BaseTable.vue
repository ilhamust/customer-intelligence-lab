<script setup>
defineProps({
  columns: {
    type: Array,
    required: true,
    // Format: [{ key: 'name', label: 'Nama', class: '' }]
  },
  rows: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  emptyTitle: {
    type: String,
    default: 'Tidak ada data',
  },
  emptyDescription: {
    type: String,
    default: 'Belum ada data untuk ditampilkan saat ini.',
  },
})
</script>

<template>
  <div class="w-full overflow-x-auto rounded-2xl border border-base-300 bg-base-100 shadow-xs">
    <table class="table w-full text-left border-collapse">
      <!-- Table Header -->
      <thead class="bg-base-200/70 border-b border-base-300 text-[11px] font-extrabold uppercase tracking-wider text-base-content/60">
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3.5 select-none"
            :class="col.class || ''"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>

      <!-- Table Body -->
      <tbody class="divide-y divide-base-300 text-xs text-base-content">
        <!-- Loading State -->
        <tr v-if="loading">
          <td :colspan="columns.length" class="p-8 text-center">
            <BaseLoading text="Memuat data tabel..." />
          </td>
        </tr>

        <!-- Empty State -->
        <tr v-else-if="rows.length === 0">
          <td :colspan="columns.length" class="p-8 text-center">
            <BaseEmpty :title="emptyTitle" :description="emptyDescription" />
          </td>
        </tr>

        <!-- Data Rows -->
        <tr
          v-else
          v-for="(row, index) in rows"
          :key="row.id || index"
          class="hover:bg-base-200/40 transition-colors"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3.5 align-middle"
            :class="col.cellClass || ''"
          >
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" :index="index">
              {{ row[col.key] ?? '-' }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
