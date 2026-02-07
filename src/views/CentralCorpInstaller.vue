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
const step = ref<'requirements' | 'download' | 'error'>('requirements')
const data = ref<FetchedData>()
const errors = reactive<string[]>([])
const phpIniPath = computed(() => data.value?.phpIniPath ?? t('unknown'))

// Progress tracking for accessibility
const steps = ['requirements', 'download'] as const
const currentStepIndex = computed(() => steps.indexOf(step.value as typeof steps[number]))

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
  window.location.reload()
}

function clearError(index: number) {
  errors.splice(index, 1)
}
</script>

<template>
  <div>
    <!-- Progress indicator -->
    <nav aria-label="Installation progress" class="mb-4" v-if="!preLoading && step !== 'error'">
      <ol class="progress-steps list-unstyled d-flex justify-content-center gap-3 mb-0">
        <li 
          v-for="(s, index) in steps" 
          :key="s"
          class="progress-step"
          :class="{
            'active': index === currentStepIndex,
            'completed': index < currentStepIndex
          }"
          :aria-current="index === currentStepIndex ? 'step' : undefined"
        >
          <span class="step-number" aria-hidden="true">{{ index + 1 }}</span>
          <span class="step-label">{{ t(`${s === 'requirements' ? 'requirements.recheck' : 'download.title'}`) }}</span>
        </li>
      </ol>
    </nav>

    <!-- Error alerts -->
    <div 
      v-for="(error, index) in errors" 
      :key="index" 
      class="alert alert-danger alert-dismissible fade show d-flex align-items-start" 
      role="alert"
      aria-live="assertive"
    >
      <div class="flex-grow-1">
        <strong>{{ t('error', { error: '' }).replace(': ', '') }}:</strong> {{ error }}

        <div v-if="error.startsWith('cURL error 60:')" class="mt-2 small">
          <i18n-t keypath="help.curl60" tag="span">
            <template #docs>
              <a href="#" target="_blank" rel="noopener noreferrer" class="alert-link">
                {{ t('documentation') }}
              </a>
            </template>
            <template #path>
              <code>{{ phpIniPath }}</code>
            </template>
          </i18n-t>
        </div>
      </div>
      <button 
        type="button" 
        class="btn-close" 
        @click="clearError(index)"
        :aria-label="'Dismiss error'"
      ></button>
    </div>

    <!-- Loading state -->
    <Transition name="fade" mode="out-in">
      <div
        v-if="preLoading"
        class="text-center py-5"
        role="status"
        aria-live="polite"
      >
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;">
          <span class="visually-hidden">{{ t('loading') }}</span>
        </div>
        <p class="text-muted mb-0">{{ t('loading') }}</p>
      </div>

      <!-- Requirements step -->
      <RequirementsView
        v-else-if="data && step === 'requirements'"
        :data="data"
        :loading="loading"
        @reload="reloadPage"
        @refresh="refreshRequirements"
        @next="showDownload"
      />

      <!-- Download step -->
      <DownloadView
        v-else-if="step === 'download'"
        :loading="loading"
        @download="startDownload"
        @error="catchError"
      />
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease-in-out;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Progress steps */
.progress-steps {
  counter-reset: step;
}

.progress-step {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--bs-secondary);
  font-size: 0.875rem;
}

.progress-step.active {
  color: var(--bs-primary);
  font-weight: 600;
}

.progress-step.completed {
  color: var(--bs-success);
}

.step-number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  background: var(--bs-secondary-bg);
  font-size: 0.75rem;
  font-weight: 600;
}

.progress-step.active .step-number {
  background: var(--bs-primary);
  color: white;
}

.progress-step.completed .step-number {
  background: var(--bs-success);
  color: white;
}

.step-label {
  display: none;
}

@media (min-width: 576px) {
  .step-label {
    display: inline;
  }
}
</style>
