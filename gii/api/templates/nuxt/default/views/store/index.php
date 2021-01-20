<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator yii\gii\generators\crud\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();

$modelId = Inflector::camel2id(StringHelper::basename($generator->modelClass));
$controllerId = Inflector::camel2id(StringHelper::basename($generator->getControllerID()));
$singular = strtoupper(Inflector::singularize($modelId));
$plural = strtoupper(Inflector::pluralize($modelId));

$tableSchema = $generator->getTableSchema();
$attributes = $generator->getColumnNames();

$body = <<<JS
export const state = () => ({
  records: [],
  totalRecords: 0,
  lastError: null,
  isInitialized: false,
  isLoading: true,
});

export const mutations = {
  SET_INITIALIZED(state, status) {
    state.isInitialized = status;
  },
  SET_LOADING(state, status) {
    state.isLoading = status;
  },
  SET_RECORD(state, record) {
    const index = this.records.indexOf(record);
    if (index > -1) {
      state.records = Object.assign(state.records[index], record);
    } else {
      state.records.push(this.editedItem);
    }
  },
  SET_RECORDS(state, records) {
    state.records = records;
  },
  DELETE_RECORD(state, record) {
    const index = this.records.indexOf(record);
    state.splice(index, 1);
  },
  SET_RECORD_COUNT(state, count) {
    state.totalRecords = count;
  },
  SET_ERROR(state, error) {
    state.lastError = error;
  },
};

export const actions = {
  init({ dispatch, commit }) {
    return dispatch('load', 1).then(() => {
      commit(`SET_INITIALIZED`, true);
    });
  },
  load({ commit }, options) {
    const { sortBy, sortDesc, page, itemsPerPage } = options;
    commit(`SET_LOADING`, true);
    return this.\$axios
      .get(`{$controllerId}?page=\${page}&per-page=\${itemsPerPage}`)
      .then((res) => {
        commit(
          `SET_RECORD_COUNT`,
          parseInt(res.headers[`x-pagination-total-count`])
        );
        commit(`SET_RECORDS`, res.data);
      })
      .catch((e) => {
        commit(`SET_ERROR`, e);
      })
      .then(() => {
        commit(`SET_LOADING`, false);
      });
  },
  create({ commit }, record) {
    commit(`SET_LOADING`, true);
    return this.\$axios
      .post(`{$controllerId}`, record)
      .then((res) => {
        commit(`SET_RECORD`, record);
      })
      .catch((e) => {
        commit(`SET_ERROR`, e);
      })
      .then(() => {
        commit(`SET_LOADING`, false);
      });
  },
  update({ commit }, record) {
    commit(`SET_LOADING`, true);
    return this.\$axios
      .post(`{$controllerId}/\${record._recid}`, record)
      .then((res) => {
        commit(`SET_RECORD`, record);
      })
      .catch((e) => {
        commit(`SET_ERROR`, e);
      })
      .then(() => {
        commit(`SET_LOADING`, false);
      });
  },
  delete({ commit }, record) {
    commit(`SET_LOADING`, true);
    return this.\$axios
      .delete(`{$controllerId}/\${record._recid}`)
      .then((res) => {
        commit(`DELETE_RECORD`, record);
      })
      .catch((e) => {
        commit(`SET_ERROR`, e);
      })
      .then(() => {
        commit(`SET_LOADING`, false);
      });
  },
};
JS;

echo $body;