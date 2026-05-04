<template>
  <section class="card">
    <h2>Новая категория</h2>
    <div class="row cols-3">
      <div>
        <label>Название</label>
        <input :value="catForm.name" @input="patchCat({ name: $event.target.value })" />
      </div>
      <div>
        <label>Slug (необязательно)</label>
        <input
          :value="catForm.slug"
          placeholder="auto из названия"
          @input="patchCat({ slug: $event.target.value })"
        />
      </div>
      <div>
        <label>Родитель</label>
        <select :value="catForm.parent_id" @change="patchCat({ parent_id: $event.target.value })">
          <option value="">— корень —</option>
          <option v-for="c in categoriesFlat" :key="'p' + c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
      </div>
    </div>
    <div style="margin-top: 0.75rem">
      <button type="button" @click="$emit('save')">Создать категорию</button>
    </div>
    <p class="muted" style="margin-top: 0.75rem">
      Дерево (через API): используйте постман/ curl для PUT/DELETE категорий при необходимости.
    </p>
    <pre class="muted" style="white-space: pre-wrap; font-size: 12px">{{ JSON.stringify(categoryTree, null, 2) }}</pre>
  </section>
</template>

<script>
export default {
  name: 'CategorySection',
  props: {
    catForm: { type: Object, required: true },
    categoriesFlat: { type: Array, default: () => [] },
    categoryTree: { default: null },
  },
  emits: ['update:catForm', 'save'],
  methods: {
    patchCat(partial) {
      this.$emit('update:catForm', { ...this.catForm, ...partial });
    },
  },
};
</script>
