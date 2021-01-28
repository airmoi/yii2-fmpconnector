<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator airmoi\yii2fmconnector\gii\api\nuxt\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();

$modelClass = StringHelper::basename($generator->modelClass);
$modelId = Inflector::camel2id($modelClass);
$storeId = Inflector::camel2id($modelClass, '_');
$moduleName = Inflector::camel2words($modelClass);

$controllerId = $generator->getControllerID();
$controllerName = Inflector::id2camel($controllerId);

$tableSchema = $generator->getTableSchema();

$jsonHeaders = $generator->generateDataTableHeaders();

$body = <<<JS
<template>
  <div class="$modelId-index">
    <v-skeleton-loader
      v-if="!isInitialized"
      class="mx-auto"
      type="table"
    ></v-skeleton-loader>
    <v-data-table
      v-else
      :options.sync="options"
      :loading="isLoading"
      :headers="headers"
      :items="records"
      :server-items-length="totalRecords"
    >
      <template v-slot:top>
        <v-toolbar flat>
          <v-toolbar-title>$moduleName</v-toolbar-title>
          <v-spacer></v-spacer>
          <v-responsive>
            <form @submit.prevent="loadData">
              <v-text-field
                v-model="search"
                append-icon="mdi-magnify"
                label="Search"
                single-line
                hide-details
              ></v-text-field>
            </form>
          </v-responsive>
          <v-spacer></v-spacer>
          <v-dialog v-model="dialog">
            <template v-slot:activator="{ on, attrs }">
              <v-btn color="primary" dark v-bind="attrs" v-on="on">
                Create {$moduleName}
              </v-btn>
            </template>
            <{$controllerId}-form
              :record="editedItem"
              :title="formTitle"
              @save="save(\$event)"
              @close="close"
            ></{$controllerId}-form>
          </v-dialog>
        </v-toolbar>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-icon small class="mr-2" @click="editItem(item)"> mdi-pencil</v-icon>
        <v-icon small @click="deleteItem(item)"> mdi-delete</v-icon>
      </template>
    </v-data-table>
  </div>
</template>

<script>
import {$controllerName}Form from '@/components/{$controllerId}/form';

export default {
  name: '{$controllerName}Index',
  auth: false,
  components: { {$controllerName}Form },
  data: () => ({
    dialog: false,
    dialogDelete: false,
    search: null,
    headers: $jsonHeaders,
    options: {
      page: 1,
      itemsPerPage: 10,
      sortBy: [],
      sortDesc: [],
      groupBy: [],
      groupDesc: [],
      mustSort: false,
      multiSort: false,
    },
    models: [],
    editedIndex: -1,
    editedItem: null,
    error: undefined,
  }),
  computed: {
    records() {
      return this.\$store.state.$storeId.records;
    },
    totalRecords() {
      return this.\$store.state.$storeId.totalRecords;
    },
    isLoading() {
      return this.\$store.state.$storeId.isLoading;
    },
    isInitialized() {
      return this.\$store.state.$storeId.isInitialized;
    },
    formTitle() {
      return this.editedIndex === -1 ? 'New $moduleName' : 'Edit $moduleName';
    },
  },
  watch: {
    options: {
      handler() {
        this.loadData();
      },
      deep: true,
    },
    dialog(val) {
      val || this.close();
    },
    dialogDelete(val) {
      val || this.closeDelete();
    },
  },
  created() {
    this.loadData();
  },
  methods: {
    loadData() {
      this.\$store.dispatch(`$storeId/load`, {
        options: this.options,
        search: this.search,
      });
    },
    save(item) {
      const action = item._recid ? 'update' : 'create';
      this.\$store.dispatch(`$storeId/\${action}`, item).then(() => {
        if (this.\$store.state.$storeId.lastError) {
          // Todo display error ?
        } else {
          this.close();
        }
      });
    },
    editItem(item) {
      this.editedItem = Object.assign({}, item);
      this.editedIndex = this.models.indexOf(item);
      this.dialog = true;
    },
    deleteItem(item) {
      this.editedItem = Object.assign({}, item);
      this.editedIndex = this.models.indexOf(item);
      this.dialogDelete = true;
    },
    deleteItemConfirm() {
      this.\$store.dispatch(`$storeId/delete`, this.editedIndex);
      this.closeDelete();
    },
    close() {
      this.dialog = false;
      this.\$nextTick(() => {
        this.editedItem = this.\$store.state.$storeId.defaultRecord;
        this.editedIndex = -1;
      });
    },
    closeDelete() {
      this.dialogDelete = false;
      this.\$nextTick(() => {
        this.editedIndex = -1;
      });
    },
  },
};
</script>

<style scoped></style>
JS;
echo $body;