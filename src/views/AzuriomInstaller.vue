<script setup lang="ts">
import type { FetchedData } from '@/api'

import { StatusError } from 'itty-fetcher'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { baseFetch, download } from '@/api'
import DownloadView from '@/views/DownloadView.vue'
import RequirementsView from '@/views/RequirementsView.vue'

const { t } = useI18n({ useScope: 'global' })

const preLoading = ref(true)
const loading = ref(false)
const step = ref('requirements')
const data = ref<FetchedData>()
const errors = reactive<string[]>([])
const phpIniPath = computed(() => data.value?.phpIniPath ?? t('unknown'))

onMounted(() => refreshRequirements())

async function refreshRequirements() {
  errors.length = 0
  loading.value = true

  try {
    const response = await baseFetch()

    if (!response.requirements) {
      errors.push(t('error', { error: 'No data in response' }))
      step.value = 'error'
      preLoading.value = false
      return
    }

    data.value = response
    loading.value = false
  } catch (e) {
    catchError(e)
  }

  preLoading.value = false
}

function showDownload() {
  step.value = 'download'
}

async function startDownload() {
  loading.value = true
  errors.length = 0

  try {
    await download()

    setTimeout(() => {
      loading.value = false

      reloadPage()
    }, 750)
  } catch (e) {
    catchError(e)
  }
}

function catchError(error: unknown) {
  loading.value = false

  if (preLoading.value) {
    step.value = 'error'
  }

  if (error instanceof StatusError) {
    errors.push(error.message)
    return
  }

  errors.push(t('error', { error }))
}

function reloadPage() {
  // Force reloads on supported browsers. On other browsers, the boolean
  // will just be ignored.
  // eslint-disable-next-line @typescript-eslint/ban-ts-comment
  // @ts-ignore
  window.location.reload(true)
}
</script>

<template>
  <div>
    <h1 class="text-center display-4 fw-semibold mb-3">{{ $t('title') }}</h1>

    <div v-for="error in errors" :key="error" class="alert alert-danger" role="alert">
      {{ error }}

      <i18n-t keypath="help.curl60" tag="div" v-if="error.startsWith('cURL error 60:')">
        <template #docs>
          <a href="https://azuriom.com/docs/faq" target="_blank" rel="noopener noreferrer">
            {{ $t('docs') }}
          </a>
        </template>
        <template #path>{{ phpIniPath }}</template>
      </i18n-t>
    </div>

    <transition name="fade" mode="out-in">
      <div
        v-if="preLoading"
        class="d-flex flex-column align-items-center justify-content-center"
        style="height: 300px"
      >
        <div class="spinner-border spinner-border-lg mb-3" />

        <h2>{{ t('loading') }}</h2>
      </div>

      <RequirementsView
        v-else-if="data && step === 'requirements'"
        :data="data"
        :loading="loading"
        @reload="reloadPage"
        @refresh="refreshRequirements"
        @next="showDownload"
      />

      <DownloadView
        v-else-if="step === 'download'"
        :loading="loading"
        @download="startDownload"
        @error="catchError"
      />
    </transition>

    <hr />
  </div>
</template>

<style scoped lang="scss">
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.1s ease-in-out;
}

.fade-enter,
.fade-leave-active {
  opacity: 0;
}
</style>
