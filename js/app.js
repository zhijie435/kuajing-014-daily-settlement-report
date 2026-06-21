const { createApp, ref, reactive, computed, onMounted, watch, nextTick } = Vue;
const { ElMessage, ElMessageBox, ElLoading } = ElementPlus;

const API_BASE = 'api';

const app = createApp({
  setup() {
    const loading = ref(false);
    const detailLoading = ref(false);
    const checkLoading = ref(false);
    const checkDialogVisible = ref(false);

    const todayDate = ref(new Date().toISOString().split('T')[0]);

    const tableData = ref([]);
    const currentPage = ref(1);
    const pageSize = ref(20);
    const total = ref(0);
    const summary = ref({});

    const dateRange = ref([]);
    const filterForm = reactive({
      checkStatus: '',
      settlementStatus: '',
    });

    const expandRowKeys = ref([]);
    const detailData = ref([]);
    const detailSummary = ref({});
    const currentExpandedDate = ref('');
    const currentExpandedRow = ref(null);

    const checkForm = reactive({
      check_status: 1,
      check_remark: '',
    });
    const checkRow = ref({});
    const checkFormRef = ref(null);
    const forceRecheck = ref(false);

    const validateCheckRemark = (rule, value, callback) => {
      if (checkForm.check_status === 2 && (!value || !value.trim())) {
        callback(new Error('标记为核对异常时，必须填写异常原因'));
      } else {
        callback();
      }
    };

    const checkFormRules = reactive({
      check_status: [
        { required: true, message: '请选择核对结果', trigger: 'change' },
      ],
      check_remark: [
        { validator: validateCheckRemark, trigger: 'blur' },
      ],
    });

    const formatMoney = (value) => {
      if (!value && value !== 0) return '0.00';
      return Number(value).toLocaleString('zh-CN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    };

    const getCheckStatusName = (status) => {
      const map = { 0: '未核对', 1: '核对通过', 2: '核对异常' };
      return map[status] || '未知';
    };

    const getCheckStatusTag = (status) => {
      const map = { 0: 'info', 1: 'success', 2: 'danger' };
      return map[status] || 'info';
    };

    const getSettleStatusName = (status) => {
      const map = { 1: '待结算', 2: '已结算', 3: '已对账' };
      return map[status] || '未知';
    };

    const getSettleStatusTag = (status) => {
      const map = { 1: 'warning', 2: 'success', 3: 'primary' };
      return map[status] || 'info';
    };

    const getStatusName = (status) => {
      const map = { 1: '待结算', 2: '已结算', 3: '已对账' };
      return map[status] || '未知';
    };

    const getStatusTag = (status) => {
      const map = { 1: 'warning', 2: 'success', 3: 'primary' };
      return map[status] || 'info';
    };

    const getSettlementTypeName = (type) => {
      const map = { 1: '正常结算', 2: '退款', 3: '补款' };
      return map[type] || '未知';
    };

    const getSettlementTypeTag = (type) => {
      const map = { 1: 'success', 2: 'danger', 3: 'warning' };
      return map[type] || 'info';
    };

    const fetchDailyData = async () => {
      loading.value = true;
      try {
        const params = new URLSearchParams({
          page: currentPage.value,
          pageSize: pageSize.value,
        });
        if (dateRange.value && dateRange.value.length === 2) {
          params.append('startDate', dateRange.value[0]);
          params.append('endDate', dateRange.value[1]);
        }
        if (filterForm.checkStatus !== '') {
          params.append('checkStatus', filterForm.checkStatus);
        }
        if (filterForm.settlementStatus !== '') {
          params.append('settlementStatus', filterForm.settlementStatus);
        }

        const response = await fetch(`${API_BASE}/settlement_daily.php?${params}`);
        const result = await response.json();

        if (result.code === 0) {
          tableData.value = result.data.list;
          total.value = result.data.total;
          summary.value = result.data.summary || {};
        } else {
          ElMessage.error(result.msg || '查询失败');
        }
      } catch (error) {
        console.error('查询失败:', error);
        ElMessage.error('网络错误，请稍后重试');
      } finally {
        loading.value = false;
      }
    };

    const fetchDetailData = async (settlementDate) => {
      detailLoading.value = true;
      try {
        const params = new URLSearchParams({
          settlementDate: settlementDate,
        });

        const response = await fetch(`${API_BASE}/settlement_detail.php?${params}`);
        const result = await response.json();

        if (result.code === 0) {
          detailData.value = result.data.list;
          detailSummary.value = result.data.summary || {};
        } else {
          ElMessage.error(result.msg || '查询明细失败');
        }
      } catch (error) {
        console.error('查询明细失败:', error);
        ElMessage.error('网络错误，请稍后重试');
      } finally {
        detailLoading.value = false;
      }
    };

    const handleExpandChange = async (row, expandedRows) => {
      const isExpanded = expandedRows.some(r => r.id === row.id);
      
      if (isExpanded) {
        currentExpandedDate.value = row.settlement_date;
        currentExpandedRow.value = row;
        expandRowKeys.value = [row.id];
        await fetchDetailData(row.settlement_date);
      } else {
        if (currentExpandedRow.value && currentExpandedRow.value.id === row.id) {
          currentExpandedDate.value = '';
          currentExpandedRow.value = null;
        }
        expandRowKeys.value = expandedRows.map(r => r.id);
      }
    };

    const handleViewDetail = async (row) => {
      const isExpanded = expandRowKeys.value.includes(row.id);
      
      if (!isExpanded) {
        expandRowKeys.value = [row.id];
        currentExpandedDate.value = row.settlement_date;
        currentExpandedRow.value = row;
        await fetchDetailData(row.settlement_date);
      }
    };

    const handleSearch = () => {
      currentPage.value = 1;
      fetchDailyData();
    };

    const handleReset = () => {
      dateRange.value = [];
      filterForm.checkStatus = '';
      filterForm.settlementStatus = '';
      currentPage.value = 1;
      fetchDailyData();
    };

    const handleSizeChange = (size) => {
      pageSize.value = size;
      currentPage.value = 1;
      fetchDailyData();
    };

    const handleCurrentChange = (page) => {
      currentPage.value = page;
      fetchDailyData();
    };

    const handleCheck = (row, status) => {
      if (row.settlement_status === 1) {
        ElMessage.warning('该记录尚未结算，无法进行核对，请先完成结算');
        return;
      }

      checkRow.value = row;
      checkForm.check_status = status;
      checkForm.check_remark = '';
      forceRecheck.value = false;
      checkDialogVisible.value = true;

      nextTick(() => {
        if (checkFormRef.value) {
          checkFormRef.value.clearValidate();
        }
      });
    };

    const handleCheckStatusChange = (val) => {
      nextTick(() => {
        if (checkFormRef.value) {
          checkFormRef.value.clearValidate('check_remark');
          if (val === 2 && !checkForm.check_remark.trim()) {
            checkFormRef.value.validateField('check_remark');
          }
        }
      });
    };

    const resetCheckForm = () => {
      checkForm.check_status = 1;
      checkForm.check_remark = '';
      forceRecheck.value = false;
      if (checkFormRef.value) {
        checkFormRef.value.resetFields();
      }
    };

    const submitCheck = async () => {
      if (!checkFormRef.value) return;

      try {
        await checkFormRef.value.validate();
      } catch {
        ElMessage.warning('请完善核对信息后再提交');
        return;
      }

      checkLoading.value = true;
      try {
        const checkedId = checkRow.value.id;
        const checkedDate = checkRow.value.settlement_date;
        const isCurrentExpanded = currentExpandedRow.value && currentExpandedRow.value.id === checkedId;
        const savedExpandKeys = [...expandRowKeys.value];

        const requestBody = {
          id: checkedId,
          check_status: checkForm.check_status,
          check_remark: checkForm.check_remark,
        };
        if (forceRecheck.value) {
          requestBody.force_recheck = true;
        }

        const response = await fetch(`${API_BASE}/settlement_check.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(requestBody),
        });

        if (!response.ok) {
          ElMessage.error(`服务器响应异常（HTTP ${response.status}），请稍后重试`);
          return;
        }

        let result;
        try {
          result = await response.json();
        } catch {
          ElMessage.error('服务器返回数据格式异常，请稍后重试');
          return;
        }

        if (result.code === 0) {
          ElMessage.success('核对成功');
          checkDialogVisible.value = false;

          await fetchDailyData();

          await nextTick();

          expandRowKeys.value = [];
          await nextTick();
          expandRowKeys.value = savedExpandKeys;

          const updatedRow = tableData.value.find(r => r.id === checkedId);
          if (updatedRow) {
            if (isCurrentExpanded) {
              currentExpandedRow.value = updatedRow;
              await nextTick();
              await fetchDetailData(checkedDate);
            }
          }
        } else if (result.code === 1006 || result.code === 1007) {
          try {
            await ElMessageBox.confirm(
              result.msg,
              '确认操作',
              {
                confirmButtonText: '确认重新核对',
                cancelButtonText: '取消',
                type: 'warning',
              }
            );
            forceRecheck.value = true;
            checkLoading.value = false;
            await submitCheck();
            return;
          } catch {
            ElMessage.info('已取消重新核对');
          }
        } else if (result.code === 1005) {
          ElMessage.warning(result.msg);
        } else {
          ElMessage.error(result.msg || '核对失败');
        }
      } catch (error) {
        console.error('核对失败:', error);
        ElMessage.error('网络错误，请稍后重试');
      } finally {
        checkLoading.value = false;
      }
    };

    const handleExportDaily = () => {
      const params = new URLSearchParams({
        type: 'daily',
        format: 'excel',
      });
      if (dateRange.value && dateRange.value.length === 2) {
        params.append('startDate', dateRange.value[0]);
        params.append('endDate', dateRange.value[1]);
      }
      if (filterForm.checkStatus !== '') {
        params.append('checkStatus', filterForm.checkStatus);
      }
      
      window.open(`${API_BASE}/export.php?${params}`, '_blank');
      ElMessage.success('正在导出汇总报表...');
    };

    const handleExportDetail = () => {
      if (!currentExpandedDate.value) {
        ElMessage.warning('请先展开一条日结算记录查看明细');
        return;
      }
      
      const params = new URLSearchParams({
        type: 'detail',
        format: 'excel',
        settlementDate: currentExpandedDate.value,
      });
      
      window.open(`${API_BASE}/export.php?${params}`, '_blank');
      ElMessage.success('正在导出明细报表...');
    };

    onMounted(() => {
      const endDate = new Date();
      const startDate = new Date();
      startDate.setDate(startDate.getDate() - 29);
      
      dateRange.value = [
        startDate.toISOString().split('T')[0],
        endDate.toISOString().split('T')[0],
      ];
      
      fetchDailyData();
    });

    return {
      loading,
      detailLoading,
      checkLoading,
      checkDialogVisible,
      todayDate,
      tableData,
      currentPage,
      pageSize,
      total,
      summary,
      dateRange,
      filterForm,
      expandRowKeys,
      detailData,
      detailSummary,
      currentExpandedDate,
      checkForm,
      checkFormRef,
      checkFormRules,
      checkRow,
      forceRecheck,
      formatMoney,
      getCheckStatusName,
      getCheckStatusTag,
      getSettleStatusName,
      getSettleStatusTag,
      getStatusName,
      getStatusTag,
      getSettlementTypeName,
      getSettlementTypeTag,
      handleExpandChange,
      handleViewDetail,
      handleSearch,
      handleReset,
      handleSizeChange,
      handleCurrentChange,
      handleCheck,
      handleCheckStatusChange,
      resetCheckForm,
      submitCheck,
      handleExportDaily,
      handleExportDetail,
      Search: ElementPlusIconsVue.Search,
      Refresh: ElementPlusIconsVue.Refresh,
      Download: ElementPlusIconsVue.Download,
    };
  },
});

for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component);
}

app.use(ElementPlus);
app.mount('#app');
