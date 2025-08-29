<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="p-8 bg-white rounded shadow text-center">
      <h1 class="text-3xl font-bold mb-4">Welcome to Inertia + Vue 3 + Tailwind + HeadlessUI</h1>
      <button @click="open" class="px-4 py-2 bg-blue-600 text-white rounded">Open Dialog</button>
      <HeadlessUIDialog v-model="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black bg-opacity-50" aria-hidden="true"></div>
        <HeadlessUIDialogPanel class="bg-white p-6 rounded shadow z-10">
          <h2 class="text-xl font-semibold mb-2">HeadlessUI Dialog</h2>
          <p>This is a modal dialog using HeadlessUI.</p>
          <button @click="close" class="mt-4 px-4 py-2 bg-gray-700 text-white rounded">Close</button>
        </HeadlessUIDialogPanel>
      </HeadlessUIDialog>
    </div>
  </div>
</template>

<script setup>
import { Dialog as HeadlessUIDialog, DialogPanel as HeadlessUIDialogPanel } from '@headlessui/vue'
import { ref } from 'vue'

const isOpen = ref(false)
const open = () => { isOpen.value = true }
const close = () => { isOpen.value = false }
</script>