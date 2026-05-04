<template>
  <section class="card">
    <h2>Товары</h2>
    <div class="row cols-3">
      <div>
        <label>Категория (slug)</label>
        <input
          :value="filters.category"
          placeholder="electronics"
          @change="applyFiltersAndRefresh({ category: $event.target.value })"
        />
      </div>
      <div>
        <label>В наличии</label>
        <select :value="filters.in_stock" @change="applyFiltersAndRefresh({ in_stock: $event.target.value })">
          <option value="">Все</option>
          <option value="1">Да</option>
          <option value="0">Нет</option>
        </select>
      </div>
      <div>
        <label>На странице</label>
        <select
          :value="filters.per_page"
          @change="applyFiltersAndRefresh({ per_page: Number($event.target.value) })"
        >
          <option :value="10">10</option>
          <option :value="15">15</option>
          <option :value="25">25</option>
        </select>
      </div>
    </div>
    <p v-if="aggregates" class="muted">
      В наличии (по фильтру категории): {{ aggregates.in_stock_count }} шт., суммарная стоимость:
      {{ aggregates.in_stock_total_value }}
    </p>
    <p v-if="loading" class="muted">Загрузка…</p>
    <table v-if="products.length">
      <thead>
        <tr>
          <th>ID</th>
          <th>Название</th>
          <th>Содержание</th>
          <th>Цена</th>
          <th>Категория</th>
          <th>Наличие</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in products" :key="p.id">
          <td>{{ p.id }}</td>
          <td>{{ p.name }}</td>
          <td>{{ p.content }}</td>
          <td>{{ p.price }}</td>
          <td>{{ p.category ? p.category.name + ' (' + p.category.slug + ')' : '—' }}</td>
          <td>{{ p.in_stock ? 'да' : 'нет' }} ({{ p.quantity }})</td>
          <td>
            <button type="button" class="secondary" @click="$emit('edit', p)">Изменить</button>
            <button type="button" class="danger" @click="$emit('delete', p.id)">Удалить</button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else class="muted">Нет данных</p>
    <div
      v-if="pagination && pagination.total_pages > 1"
      style="margin-top: 0.75rem; display: flex; gap: 0.5rem; align-items: center"
    >
      <button
        type="button"
        class="secondary"
        :disabled="filters.page <= 1"
        @click="applyFiltersAndRefresh({ page: filters.page - 1 })"
      >
        Назад
      </button>
      <span class="muted">Стр. {{ pagination.current_page }} / {{ pagination.total_pages }}</span>
      <button
        type="button"
        class="secondary"
        :disabled="filters.page >= pagination.total_pages"
        @click="applyFiltersAndRefresh({ page: filters.page + 1 })"
      >
        Вперёд
      </button>
    </div>
  </section>
</template>

<script>
export default {
  name: 'ProductsListSection',
  props: {
    filters: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    pagination: { type: Object, default: null },
    aggregates: { type: Object, default: null },
    loading: Boolean,
  },
  emits: ['update:filters', 'edit', 'delete', 'refresh'],
  methods: {
    patchFilters(patch) {
      const next = { ...this.filters, ...patch };
      if ('category' in patch || 'in_stock' in patch || 'per_page' in patch) {
        next.page = 1;
      }
      this.$emit('update:filters', next);
    },
    applyFiltersAndRefresh(patch) {
      this.patchFilters(patch);
      this.$emit('refresh');
    },
  },
};
</script>
