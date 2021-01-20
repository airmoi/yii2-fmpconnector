<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\crud\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();
$modelId = Inflector::camel2id(StringHelper::basename($generator->modelClass));
$controllerId = Inflector::camel2id(StringHelper::basename($generator->getControllerID()));

$tableSchema = $generator->getTableSchema();

$attributes = $generator->getColumnNames();

$headers = [];
foreach ($attributes as $attribute) {
    $headers[] = [
        'text' => Inflector::camel2words($attribute),
        'value' => $attribute
    ];
}
$headers[] = [
    'text' => 'Actions',
    'value' => 'actions',
    'sortable' => false
];
$jsonHeaders = json_encode($headers, JSON_PRETTY_PRINT);

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
          <v-toolbar-title>$modelId</v-toolbar-title>
          <v-divider class="mx-4" inset vertical></v-divider>
          <v-spacer></v-spacer>
        </v-toolbar>
        <v-dialog v-model="dialog" max-width="600px">
          <template v-slot:activator="{ on, attrs }">
            <v-btn color="primary" dark v-bind="attrs" v-on="on">
              Create $modelId
            </v-btn>
          </template>
          <{$controllerId}-form @close-dialog="dialog = false"></{$controllerId}-form>
        </v-dialog>
      </template>
      <template v-slot:item.actions="{ item }">
        <v-icon small class="mr-2" @click="editItem(item)"> mdi-pencil</v-icon>
        <v-icon small @click="deleteItem(item)"> mdi-delete</v-icon>
      </template>
    </v-data-table>
  </div>
</template>

<script>
import {$controllerId}Form from '@/components/{$controllerId}/form';

export default {
  name: '{$controllerId}Index',
  auth: false,
  components: { {$controllerId}Form },
  data: () => ({
    dialog: false,
    dialogDelete: false,
    headers: $jsonHeaders,
    options: {},
    models: [],
    editedIndex: -1,
    editedModel: null,
    error: undefined,
  }),
  computed: {
    records() {
      return this.\$store.state.$modelId.records;
    },
    totalRecords() {
      return this.\$store.state.$modelId.totalRecords;
    },
    isLoading() {
      return this.\$store.state.$modelId.isLoading;
    },
    isInitialized() {
      return this.\$store.state.$modelId.isInitialized;
    },
    formTitle() {
      return this.editedIndex === -1 ? 'New $modelId' : 'Edit $modelId';
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
    this.initialize();
  },
  methods: {
    initialize() {
      this.\$store.dispatch(`$modelId/init`);
    },
    loadData() {
      this.\$store.dispatch(`$modelId/load`, this.options);
    },
    editItem(item) {
      this.editedIndex = this.models.indexOf(item);
      this.editedItem = Object.assign({}, item);
      this.dialog = true;
    },
    deleteItem(item) {
      this.editedIndex = this.models.indexOf(item);
      this.editedItem = Object.assign({}, item);
      this.dialogDelete = true;
    },
    deleteItemConfirm() {
      this\$store.dispatch(`$modelId/delete`, this.editedIndex);
      this.closeDelete();
    },
    close() {
      this.dialog = false;
      this.\$nextTick(() => {
        // this.editedItem = Object.assign({}, this.defaultItem)
        this.editedIndex = -1;
      });
    },
    closeDelete() {
      this.dialogDelete = false;
      this.\$nextTick(() => {
        // this.editedItem = Object.assign({}, this.defaultItem)
        this.editedIndex = -1;
      });
    },
  },
};
</script>

<style scoped></style>
JS;
echo $body;