<script setup>
import { onMounted } from 'vue'

onMounted(() => {
  window.Echo.channel('transactions')
    .listen('.created', (e) => {
      console.log('New transaction:', e) // replace with toast/UI later
    })
})
</script>

<template>
  <div class="p-6">Listening for transactions…</div>
</template>
