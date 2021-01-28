<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/* @var $this yii\web\View */
/* @var $generator airmoi\yii2fmconnector\gii\api\nuxt\Generator */

$urlParams = $generator->generateUrlParams();
$nameAttribute = $generator->getNameAttribute();

$modelId = Inflector::camel2id(StringHelper::basename($generator->modelClass));
$controllerId = Inflector::camel2id(StringHelper::basename($generator->getControllerID()));

$singular = strtoupper(Inflector::singularize($modelId));
$plural = strtoupper(Inflector::pluralize($modelId));

$tableSchema = $generator->getTableSchema();
$attributes = $generator->getColumnNames();
$defaultRecord = $generator->getDefaultRecord();

$body = <<<JS
import { isEqual } from 'lodash';

export const state = () => ({
  records: [],
  defaultRecord: $defaultRecord,
  totalRecords: 0,
  lastError: null,
  isInitialized: false,
  isLoading: true,
  pagination: {
    search: null,
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
  },
});

export const mutations = {
  SET_INITIALIZED(state, status) {
    state.isInitialized = status;
  },
  SET_LOADING(state, status) {
    state.isLoading = status;
  },
  SET_PAGINATION(state, pagination) {
    state.pagination = pagination;
  },
  SET_RECORD(state, payload) {
    const record = state.records.find(
      (record) => record._recid === payload._recid
    );
    if (record) {
      Object.assign(record, payload);
    } else {
      state.records.push({ ...payload });
    }
  },
  SET_RECORDS(state, records) {
    state.records = records;
  },
  DELETE_RECORD(state, record) {
    const index = state.records.indexOf(record);
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
  load({ commit, state }, payload) {
    const { sortBy, sortDesc, page, itemsPerPage } = payload.options;

    let searchFilter;
    if (payload.search) {
      searchFilter = `&filter[search]=\${payload.search}`;
    }

    let sortParams = '';
    sortBy.forEach((attr, i) => {
      const sortOrder = sortDesc[i] ? '-' : '';
      sortParams += `&sort=\${sortOrder}\${attr}`;
    });

    if (state.isInitialized && isEqual(state.pagination, payload)) {
      return Promise.resolve();
    }

    commit(`SET_LOADING`, true);
    commit(`SET_ERROR`, null);
    return this.\$axios
      .get(
        `{$controllerId}?page=\${page}&per-page=\${itemsPerPage}\${sortParams}\${searchFilter}`
      )
      .then((res) => {
        commit(
          `SET_RECORD_COUNT`,
          parseInt(res.headers[`x-pagination-total-count`])
        );
        commit(`SET_PAGINATION`, payload);
        commit(`SET_RECORDS`, res.data);
      })
      .catch((e) => {
        commit(`SET_ERROR`, e);
      })
      .then(() => {
        commit(`SET_INITIALIZED`, true);
        commit(`SET_LOADING`, false);
      });
  },
  create({ commit }, record) {
    commit(`SET_LOADING`, true);
    commit(`SET_ERROR`, null);
    return this.\$axios
      .post(`{$controllerId}`, record)
      .then((res) => {
        commit(`SET_RECORD`, res.data);
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
    commit(`SET_ERROR`, null);
    return this.\$axios
      .put(`{$controllerId}/\${record._recid}`, record)
      .then((res) => {
        commit(`SET_RECORD`, res.data);
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
    commit(`SET_ERROR`, null);
    return this.\$axios
      .delete(`{$controllerId}/\${record._recid}`)
      .then(() => {
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