<script setup lang="ts">
import type { FetchedData } from '@/api'

import {
  BIconArrowClockwise,
  BIconArrowRight,
  BIconCheckCircleFill,
  BIconInfoCircleFill,
  BIconExclamationTriangleFill,
  BIconXCircleFill,
} from 'bootstrap-icons-vue'
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'

const { t } = useI18n()

const props = defineProps<{ loading: boolean; data: FetchedData }>()
const emit = defineEmits<{ refresh: []; reload: []; next: [] }>()

// Count requirements status
const requirementStats = computed(() => {
  const entries = Object.entries(props.data.requirements)
  const passed = entries.filter(([, status]) => status).length
  return { passed, total: entries.length }
})

function nextStep() {
  if (props.data.extracted) {
    setTimeout(() => emit('reload'))
    return
  }
  emit('next')
}

function markdownify(text: string) {
  return text
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\\n/g, '<br>')
}

function translateRequirement(requirement: string) {
  if (requirement.startsWith('extension-')) {
    return t('requirements.extension', {
      extension: requirement.replace('extension-', ''),
    })
  }

  if (requirement.startsWith('function-')) {
    return t('requirements.function', {
      function: requirement.replace('function-', ''),
    })
  }

  return t(`requirements.${requirement}`, { version: props.data.minPhpVersion })
}

function translateRequirementHelp(requirement: string) {
  if (requirement.startsWith('extension-')) {
    const v = props.data.phpVersion

    return t('requirements.help.extension', {
      command: `apt install curl php${v}-mysql php${v}-pgsql php${v}-sqlite3 php${v}-bcmath php${v}-mbstring php${v}-xml php${v}-curl php${v}-zip php${v}-gd`,
    })
  }

  if (requirement.startsWith('function-')) {
    return t('requirements.help.function', {
      path: props.data.phpIniPath || '',
    })
  }

  if (requirement === 'writable') {
    return t('requirements.help.writable', {
      command: `chmod -R 755 ${props.data.path} && chown -R www-data:www-data ${props.data.path}`,
    })
  }

  return requirement === 'writable' && !props.data.htaccess
    ? t('requirements.help.htaccess')
    : t(`requirements.help.${requirement}`, {
        docs: `<a href="https://centralcorp.github.io/" target="_blank" rel="noopener noreferrer">${t('documentation')}</a>`,
      })
}

function hasRequirementHelp(requirement: string) {
  if (requirement === 'php') {
    return false
  }

  if (!props.data.windows) {
    return true
  }

  return requirement !== 'writable' && !requirement.startsWith('extension-')
}

function getRequirementId(requirement: string) {
  return `req-${requirement.replace(/[^a-z0-9]/gi, '-')}`
}
</script>

<template>
  <div>
    <!-- Welcome message -->
    <p class="text-center text-muted mb-4">{{ t('welcome') }}</p>

    <!-- Requirements not met -->
    <div v-if="!data.compatible">
      <!-- Summary -->
      <div class="text-center mb-3">
        <span class="badge bg-secondary fs-6">
          {{ requirementStats.passed }} / {{ requirementStats.total }} requirements met
        </span>
      </div>

      <!-- Requirements list -->
      <ul 
        class="list-group mb-4" 
        role="list" 
        aria-label="Server requirements checklist"
      >
        <li
          v-for="(reqStatus, requirement) in data.requirements"
          :key="requirement"
          :id="getRequirementId(String(requirement))"
          class="list-group-item"
          :class="{ 'list-group-item-danger': !reqStatus }"
        >
          <div class="d-flex align-items-start gap-3">
            <!-- Status icon -->
            <span 
              class="flex-shrink-0 fs-5 mt-1"
              :class="reqStatus ? 'text-success' : 'text-danger'"
              :aria-label="reqStatus ? 'Passed' : 'Failed'"
            >
              <BIconCheckCircleFill v-if="reqStatus" aria-hidden="true" />
              <BIconXCircleFill v-else aria-hidden="true" />
            </span>

            <!-- Requirement details -->
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-medium">
                  {{ translateRequirement(String(requirement)) }}
                </span>
                
                <!-- PHP version display -->
                <span 
                  v-if="requirement === 'php'"
                  class="badge"
                  :class="reqStatus ? 'bg-success' : 'bg-danger'"
                  :title="data.phpFullVersion"
                >
                  {{ data.phpVersion }}
                </span>
              </div>

              <!-- Help text for failed requirements -->
              <div 
                v-if="!reqStatus && hasRequirementHelp(String(requirement))" 
                class="mt-2 small text-muted"
                role="note"
              >
                <BIconInfoCircleFill class="text-info me-1" aria-hidden="true" />
                <span v-html="markdownify(translateRequirementHelp(String(requirement)))" />
              </div>
            </div>
          </div>
        </li>
      </ul>

      <!-- Warning alert -->
      <div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
        <BIconExclamationTriangleFill class="flex-shrink-0 fs-5" aria-hidden="true" />
        <span>{{ t('requirements.missing') }}</span>
      </div>

      <!-- Recheck button -->
      <div class="text-center">
        <button
          type="button"
          @click="emit('refresh')"
          :disabled="loading"
          class="btn btn-secondary rounded-pill px-4"
          :aria-busy="loading"
        >
          <BIconArrowClockwise :class="{ 'spin': loading }" aria-hidden="true" />
          <span class="ms-2">{{ t('requirements.recheck') }}</span>
          <span v-if="loading" class="visually-hidden">Checking requirements...</span>
        </button>
      </div>
    </div>

    <!-- Requirements met -->
    <div v-else class="text-center">
      <!-- Success message -->
      <div class="mb-4">
        <BIconCheckCircleFill class="text-success fs-1 mb-3" aria-hidden="true" />
        <p class="text-success fw-medium mb-0">{{ t('requirements.success') }}</p>
      </div>

      <!-- Continue button -->
      <button 
        type="button"
        @click="nextStep" 
        class="btn btn-primary btn-lg rounded-pill px-5"
        autofocus
      >
        {{ t('continue') }}
        <BIconArrowRight class="ms-2" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.list-group-item {
  border-left-width: 3px;
}

.list-group-item-danger {
  border-left-color: var(--bs-danger);
}

.list-group-item:not(.list-group-item-danger) {
  border-left-color: var(--bs-success);
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.spin {
  animation: spin 1s linear infinite;
}

code {
  background: var(--bs-secondary-bg);
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.8125em;
  word-break: break-all;
}
</style>
