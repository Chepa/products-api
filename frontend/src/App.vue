<template>
  <div>
    <PanelAlerts :msg="msg" :err="err" />
    <TokenSection
      :token="token"
      :token-error="tokenError"
      @update:token="token = $event"
      @save="saveToken"
      @generate="generateToken"
      @clear="clearToken"
    />
    <template v-if="token">
      <ProductsListSection
        v-model:filters="filters"
        :products="products"
        :pagination="pagination"
        :aggregates="aggregates"
        :loading="loading"
        @refresh="refreshProducts"
        @edit="editProduct"
        @delete="deleteProduct"
      />
      <ProductFormSection
        v-model:product-form="productForm"
        :editing-id="editingId"
        :categories-flat="categoriesFlat"
        @save="saveProduct"
        @cancel="resetProductForm"
      />
      <CategorySection
        v-model:cat-form="catForm"
        :categories-flat="categoriesFlat"
        :category-tree="categoryTree"
        @save="saveCategory"
      />
    </template>
  </div>
</template>

<script>
import PanelAlerts from './components/PanelAlerts.vue';
import TokenSection from './components/TokenSection.vue';
import ProductsListSection from './components/ProductsListSection.vue';
import ProductFormSection from './components/ProductFormSection.vue';
import CategorySection from './components/CategorySection.vue';

export default {
  name: 'App',
  components: {
    PanelAlerts,
    TokenSection,
    ProductsListSection,
    ProductFormSection,
    CategorySection,
  },
  props: {
    apiBase: { type: String, required: true },
  },
  data() {
    return {
      token: localStorage.getItem('pa_token') || '',
      tokenError: '',
      products: [],
      pagination: null,
      aggregates: null,
      loading: false,
      filters: { category: '', in_stock: '', page: 1, per_page: 15 },
      categoriesFlat: [],
      categoryTree: [],
      msg: '',
      err: '',
      productForm: { name: '', content: '', price: '', quantity: '', category_slug: '', in_stock: true },
      editingId: null,
      catForm: { name: '', slug: '', parent_id: '' },
    };
  },
  computed: {
    authHeaders() {
      const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
      if (this.token) h.Authorization = 'Bearer ' + this.token;
      return h;
    },
  },
  mounted() {
    this.loadMeta();
    if (this.token) {
      this.refreshProducts();
    }
  },
  methods: {
    saveToken() {
      localStorage.setItem('pa_token', this.token.trim());
      this.tokenError = '';
      this.refreshProducts();
      this.loadMeta();
    },
    clearToken() {
      this.token = '';
      localStorage.removeItem('pa_token');
    },
    async loadMeta() {
      if (!this.token) return;
      this.err = '';
      try {
        const [c, t] = await Promise.all([
          fetch(this.apiBase + '/categories', { headers: this.authHeaders }),
          fetch(this.apiBase + '/categories/tree', { headers: this.authHeaders }),
        ]);
        const jc = await c.json();
        const jt = await t.json();
        if (!jc.success) throw new Error(jc.error?.message || 'categories');
        if (!jt.success) throw new Error(jt.error?.message || 'tree');
        this.categoriesFlat = jc.data;
        this.categoryTree = jt.data;
      } catch (e) {
        this.err = String(e.message || e);
      }
    },
    async refreshProducts() {
      if (!this.token) return;
      this.loading = true;
      this.err = '';
      try {
        const p = new URLSearchParams();
        if (this.filters.category) p.set('category', this.filters.category);
        if (this.filters.in_stock !== '') p.set('in_stock', this.filters.in_stock);
        p.set('page', String(this.filters.page));
        p.set('per_page', String(this.filters.per_page));
        const res = await fetch(this.apiBase + '/products?' + p.toString(), { headers: this.authHeaders });
        const j = await res.json();
        if (res.status === 401) {
          this.tokenError = 'Нужен валидный Bearer token';
          this.products = [];
          return;
        }
        if (!j.success) throw new Error(j.error?.message || 'Ошибка API');
        this.products = j.data;
        this.pagination = j.pagination;
        this.aggregates = j.aggregates;
      } catch (e) {
        this.err = String(e.message || e);
      } finally {
        this.loading = false;
      }
    },
    async generateToken() {
      this.err = '';
      try {
        const res = await fetch(this.apiBase + '/tokens/generate', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ name: 'ui' }),
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.error?.message || 'generate');
        this.token = j.data.token;
        this.saveToken();
        this.msg = 'Токен создан и сохранён локально';
        setTimeout(() => {
          this.msg = '';
        }, 2500);
      } catch (e) {
        this.err = String(e.message || e);
      }
    },
    resetProductForm() {
      this.editingId = null;
      this.productForm = { name: '', content: '', price: '', quantity: '', category_slug: '', in_stock: true };
    },
    editProduct(p) {
      this.editingId = p.id;
      this.productForm = {
        name: p.name,
        content: p.content || '',
        price: p.price,
        quantity: String(p.quantity),
        category_slug: p.category ? p.category.slug : '',
        in_stock: !!p.in_stock,
      };
    },
    async saveProduct() {
      this.err = '';
      try {
        const body = {
          name: this.productForm.name,
          content: this.productForm.content,
          price: Number(this.productForm.price),
          quantity: parseInt(this.productForm.quantity, 10),
          category_slug: this.productForm.category_slug,
          in_stock: this.productForm.in_stock ? 1 : 0,
        };
        const url = this.editingId ? this.apiBase + '/products/' + this.editingId : this.apiBase + '/products';
        const method = this.editingId ? 'PUT' : 'POST';
        const res = await fetch(url, {
          method,
          headers: this.authHeaders,
          body: JSON.stringify(body),
        });
        const j = await res.json();
        if (!j.success) {
          const det = j.error?.details ? JSON.stringify(j.error.details) : '';
          throw new Error((j.error?.message || 'save') + ' ' + det);
        }
        this.resetProductForm();
        await this.refreshProducts();
        await this.loadMeta();
        this.msg = 'Товар сохранён';
        setTimeout(() => {
          this.msg = '';
        }, 2000);
      } catch (e) {
        this.err = String(e.message || e);
      }
    },
    async deleteProduct(id) {
      if (!confirm('Удалить товар #' + id + '?')) return;
      this.err = '';
      try {
        const res = await fetch(this.apiBase + '/products/' + id, {
          method: 'DELETE',
          headers: this.authHeaders,
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.error?.message || 'delete');
        await this.refreshProducts();
      } catch (e) {
        this.err = String(e.message || e);
      }
    },
    resetCatForm() {
      this.catForm = { name: '', slug: '', parent_id: '' };
    },
    async saveCategory() {
      this.err = '';
      try {
        const body = { name: this.catForm.name };
        if (this.catForm.slug) body.slug = this.catForm.slug;
        if (this.catForm.parent_id !== '' && this.catForm.parent_id !== null) {
          body.parent_id = parseInt(this.catForm.parent_id, 10);
        }
        const res = await fetch(this.apiBase + '/categories', {
          method: 'POST',
          headers: this.authHeaders,
          body: JSON.stringify(body),
        });
        const j = await res.json();
        if (!j.success) {
          const det = j.error?.details ? JSON.stringify(j.error.details) : '';
          throw new Error((j.error?.message || 'save') + ' ' + det);
        }
        this.resetCatForm();
        await this.loadMeta();
        this.msg = 'Категория создана';
        setTimeout(() => {
          this.msg = '';
        }, 2000);
      } catch (e) {
        this.err = String(e.message || e);
      }
    },
  },
};
</script>
