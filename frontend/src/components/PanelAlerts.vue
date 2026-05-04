<template>
  <div>
    <p v-if="msg" class="muted">{{ msg }}</p>
    <p v-if="err" class="err">{{ err }}</p>
  </div>
</template>

<script>
export default {
  name: 'PanelAlerts',
  props: {
    msg: String,
    err: String,
  },
};
</script>
