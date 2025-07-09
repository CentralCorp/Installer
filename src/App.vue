<script setup lang="ts">
import CentralCorpInstaller from './views/CentralCorpInstaller.vue'
</script>

<template>
  <div id="app" class="container">
    <div class="row justify-content-center align-items-center py-3 py-md-5">
      <div class="content col-xl-8 col-lg-10 col-12 bg-body p-3 px-md-5 py-md-4 shadow-sm rounded">
        <div class="text-center">
          <img src="https://centralcorp.github.io/assets/img/panel.png" alt="Azuriom" class="mb-4" width="350" />
        </div>

        <CentralCorpInstaller />
      </div>
    </div>
  </div>
</template>
