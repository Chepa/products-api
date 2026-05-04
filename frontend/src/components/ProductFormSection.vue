<template>
  <section class="card">
    <h2>{{ editingId ? 'Редактировать товар #' + editingId : 'Новый товар' }}</h2>
    <div class="row cols-2">
      <div>
        <label>Название</label>
        <input :value="productForm.name" @input="patchForm({ name: $event.target.value })" />
      </div>
      <div>
        <label>Цена</label>
        <input
          :value="productForm.price"
          type="number"
          step="0.01"
          @input="patchForm({ price: $event.target.value })"
        />
      </div>
      <div>
        <label>Количество</label>
        <input
          :value="productForm.quantity"
          type="number"
          @input="patchForm({ quantity: $event.target.value })"
        />
      </div>
      <div>
        <label>Категория (slug)</label>
        <select :value="productForm.category_slug" @change="patchForm({ category_slug: $event.target.value })">
          <option value="" disabled>выберите</option>
          <option v-for="c in categoriesFlat" :key="c.id" :value="c.slug">
            {{ c.name }} ({{ c.slug }})
          </option>
        </select>
      </div>
      <div style="grid-column: 1 / -1">
        <label>Содержание</label>
        <textarea :value="productForm.content" rows="3" @input="patchForm({ content: $event.target.value })" />
      </div>
      <div>
        <label>
          <input type="checkbox" :checked="productForm.in_stock" @change="patchForm({ in_stock: $event.target.checked })" />
          В наличии
        </label>
      </div>
    </div>
    <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem">
      <button type="button" @click="$emit('save')">{{ editingId ? 'Сохранить' : 'Создать' }}</button>
      <button v-if="editingId" type="button" class="secondary" @click="$emit('cancel')">Отмена</button>
    </div>
  </section>
</template>

<script>
export default {
  name: 'ProductFormSection',
  props: {
    editingId: { default: null },
    productForm: { type: Object, required: true },
    categoriesFlat: { type: Array, default: () => [] },
  },
  emits: ['update:productForm', 'save', 'cancel'],
  methods: {
    patchForm(partial) {
      this.$emit('update:productForm', { ...this.productForm, ...partial });
    },
  },
};
</script>
