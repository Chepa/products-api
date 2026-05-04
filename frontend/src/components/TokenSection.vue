<template>
  <section class="card" style="margin-bottom: 1rem">
    <h2>Токен</h2>
    <p class="muted">
      Для API нужен Bearer token. Сид из БД: <code>dev-api-token</code> (после migrate). Можно сгенерировать новый:
    </p>
    <div class="row cols-2">
      <div>
        <label>Bearer token</label>
        <input
          :value="token"
          placeholder="вставьте токен"
          @input="$emit('update:token', $event.target.value)"
        />
      </div>
      <div style="display: flex; align-items: flex-end; gap: 0.5rem">
        <button type="button" @click="$emit('save')">Сохранить</button>
        <button type="button" class="secondary" @click="$emit('generate')">Сгенерировать</button>
        <button type="button" class="danger" @click="$emit('clear')">Очистить</button>
      </div>
    </div>
    <p v-if="tokenError" class="err">{{ tokenError }}</p>
  </section>
</template>

<script>
export default {
  name: 'TokenSection',
  props: {
    token: String,
    tokenError: String,
  },
  emits: ['update:token', 'save', 'generate', 'clear'],
};
</script>
