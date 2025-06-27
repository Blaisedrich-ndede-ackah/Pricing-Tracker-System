/**
 * Enhanced Pricing Tracker Dashboard JavaScript
 * Handles all frontend functionality for the enhanced product management system
 */

class EnhancedPricingTracker {
  constructor() {
    this.apiBase = "api/"
    this.products = []
    this.filteredProducts = []
    this.vendors = []
    this.dashboardStats = {}
    this.currentEditId = null
    this.currentVendorEditId = null
    this.filters = {
      search: "",
      vendor: "",
      status: "",
      minPrice: "",
      maxPrice: "",
      minProfit: "",
      maxProfit: "",
      dateFrom: "",
      dateTo: "",
    }

    this.init()
  }

  /**
   * Initialize the enhanced application
   */
  init() {
    this.checkAuthentication()
    this.bindEvents()
    this.loadDashboardData()
    this.loadVendors()
    this.loadProducts()
    this.setupMarkupPresets()
  }

  /**
   * Check if user is authenticated
   */
  async checkAuthentication() {
    try {
      console.log("Checking dashboard authentication...")
      const response = await fetch(this.apiBase + "auth.php", {
        method: "GET",
        credentials: "same-origin",
      })

      console.log("Dashboard auth check response status:", response.status)

      if (response.ok) {
        const data = await response.json()
        console.log("Dashboard auth check data:", data)

        if (!data.authenticated) {
          console.log("User not authenticated, redirecting to login...")
          window.location.href = "index.html"
          return false
        } else {
          console.log("User authenticated:", data.username)
          // Update welcome message
          const welcomeEl = document.getElementById("welcomeUser")
          if (welcomeEl) {
            welcomeEl.textContent = `Welcome, ${data.username}`
          }
          return true
        }
      } else {
        console.log("Auth check failed with status:", response.status)
        window.location.href = "index.html"
        return false
      }
    } catch (error) {
      console.error("Auth check failed:", error)
      window.location.href = "index.html"
      return false
    }
  }

  /**
   * Bind all event listeners
   */
  bindEvents() {
    // Header actions
    document.getElementById("logoutBtn").addEventListener("click", () => this.logout())

    // Product management
    document.getElementById("addProductBtn").addEventListener("click", () => this.showProductModal())
    document.getElementById("addVendorBtn").addEventListener("click", () => this.showVendorModal())
    document.getElementById("salesHistoryBtn").addEventListener("click", () => this.showSalesHistoryModal())
    document.getElementById("backupRestoreBtn").addEventListener("click", () => this.showBackupModal())

    // Import/Export
    document.getElementById("exportBtn").addEventListener("click", () => this.exportProducts())
    document.getElementById("importBtn").addEventListener("click", () => this.showImportModal())
    document.getElementById("templateBtn").addEventListener("click", () => this.downloadTemplate())

    // Search and filters
    document.getElementById("searchInput").addEventListener("input", (e) => this.handleSearch(e.target.value))
    document
      .getElementById("vendorFilter")
      .addEventListener("change", (e) => this.handleFilter("vendor", e.target.value))
    document
      .getElementById("statusFilter")
      .addEventListener("change", (e) => this.handleFilter("status", e.target.value))
    document.getElementById("sortSelect").addEventListener("change", (e) => this.handleSort(e.target.value))

    // Price and profit range filters
    document.getElementById("minPrice").addEventListener("input", (e) => this.handleFilter("minPrice", e.target.value))
    document.getElementById("maxPrice").addEventListener("input", (e) => this.handleFilter("maxPrice", e.target.value))
    document
      .getElementById("minProfit")
      .addEventListener("input", (e) => this.handleFilter("minProfit", e.target.value))
    document
      .getElementById("maxProfit")
      .addEventListener("input", (e) => this.handleFilter("maxProfit", e.target.value))

    // Date range filters
    document.getElementById("dateFrom").addEventListener("change", (e) => this.handleFilter("dateFrom", e.target.value))
    document.getElementById("dateTo").addEventListener("change", (e) => this.handleFilter("dateTo", e.target.value))

    // Clear filters
    document.getElementById("clearFilters").addEventListener("click", () => this.clearAllFilters())

    // Product modal controls
    document.getElementById("closeModal").addEventListener("click", () => this.hideProductModal())
    document.getElementById("cancelBtn").addEventListener("click", () => this.hideProductModal())
    document.getElementById("productForm").addEventListener("submit", (e) => this.handleProductSubmit(e))

    // Vendor modal controls
    document.getElementById("closeVendorModal").addEventListener("click", () => this.hideVendorModal())
    document.getElementById("vendorForm").addEventListener("submit", (e) => this.handleVendorSubmit(e))

    // Import modal controls
    document.getElementById("closeImportModal").addEventListener("click", () => this.hideImportModal())
    document.getElementById("importForm").addEventListener("submit", (e) => this.handleImportSubmit(e))
    document.getElementById("downloadTemplate").addEventListener("click", () => this.downloadTemplate())

    // Sales modal controls
    document.getElementById("closeSalesModal").addEventListener("click", () => this.hideSalesModal())
    document.getElementById("cancelSaleBtn").addEventListener("click", () => this.hideSalesModal())
    document.getElementById("salesForm").addEventListener("submit", (e) => this.handleSalesSubmit(e))
    document.getElementById("closeSalesHistoryModal").addEventListener("click", () => this.hideSalesHistoryModal())

    // Backup modal controls
    document.getElementById("closeBackupModal").addEventListener("click", () => this.hideBackupModal())
    document.getElementById("createBackupBtn").addEventListener("click", () => this.createBackup())
    document.getElementById("restoreForm").addEventListener("submit", (e) => this.handleRestoreSubmit(e))

    // Image upload controls
    document.getElementById("uploadImageBtn").addEventListener("click", () => this.showImageUploadModal())
    document.getElementById("closeImageUploadModal").addEventListener("click", () => this.hideImageUploadModal())
    document.getElementById("cancelImageUpload").addEventListener("click", () => this.hideImageUploadModal())
    document.getElementById("imageUploadForm").addEventListener("submit", (e) => this.handleImageUpload(e))
    document.getElementById("productImageFile").addEventListener("change", (e) => this.previewImage(e))

    // Auto-calculate pricing and sales
    document.getElementById("actualPrice").addEventListener("input", () => this.calculatePricing())
    document.getElementById("markupPercentage").addEventListener("input", () => this.calculatePricing())
    document.getElementById("quantity").addEventListener("input", () => this.calculatePricing())
    document.getElementById("quantitySold").addEventListener("input", () => this.calculateSaleProfit())
    document.getElementById("salePrice").addEventListener("input", () => this.calculateSaleProfit())

    // Markup presets
    document.querySelectorAll(".preset-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault()
        const markup = e.target.dataset.markup
        document.getElementById("markupPercentage").value = markup
        this.calculatePricing()
      })
    })

    // Close modals when clicking outside
    document.getElementById("productModal").addEventListener("click", (e) => {
      if (e.target.id === "productModal") this.hideProductModal()
    })

    document.getElementById("vendorModal").addEventListener("click", (e) => {
      if (e.target.id === "vendorModal") this.hideVendorModal()
    })

    document.getElementById("importModal").addEventListener("click", (e) => {
      if (e.target.id === "importModal") this.hideImportModal()
    })

    document.getElementById("salesModal").addEventListener("click", (e) => {
      if (e.target.id === "salesModal") this.hideSalesModal()
    })

    document.getElementById("salesHistoryModal").addEventListener("click", (e) => {
      if (e.target.id === "salesHistoryModal") this.hideSalesHistoryModal()
    })

    document.getElementById("backupModal").addEventListener("click", (e) => {
      if (e.target.id === "backupModal") this.hideBackupModal()
    })

    document.getElementById("imageUploadModal").addEventListener("click", (e) => {
      if (e.target.id === "imageUploadModal") this.hideImageUploadModal()
    })

    // Dropdown toggle
    document.querySelector(".dropdown-toggle").addEventListener("click", (e) => {
      e.preventDefault()
      e.target.parentElement.classList.toggle("active")
    })

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!e.target.closest(".dropdown")) {
        document.querySelectorAll(".dropdown").forEach((dropdown) => {
          dropdown.classList.remove("active")
        })
      }
    })
  }

  /**
   * Load dashboard summary data
   */
  async loadDashboardData() {
    try {
      const response = await fetch(this.apiBase + "dashboard.php")
      const data = await response.json()

      if (data.product_stats) {
        this.dashboardStats = data
        this.updateDashboardSummary()
      }
    } catch (error) {
      console.error("Dashboard data load error:", error)
    }
  }

  /**
   * Update dashboard summary display
   */
  updateDashboardSummary() {
    const stats = this.dashboardStats.product_stats

    document.getElementById("totalProducts").textContent = stats.total_products || 0
    document.getElementById("totalInvestment").textContent = `GHS ${(stats.total_investment || 0).toFixed(2)}`
    document.getElementById("totalProfit").textContent = `GHS ${(stats.total_potential_profit || 0).toFixed(2)}`
    document.getElementById("activeProducts").textContent = stats.active_products || 0

    // Update summary message
    const summaryText = document.getElementById("summaryText")
    if (stats.total_products > 0) {
      summaryText.innerHTML = `
        You have <strong>${stats.total_products}</strong> products listed with a total potential profit of 
        <strong>GHS ${(stats.total_potential_profit || 0).toFixed(2)}</strong>. 
        ${stats.active_products} are currently active.
      `
    } else {
      summaryText.textContent = "No products added yet. Start by adding your first product!"
    }
  }

  /**
   * Load vendors
   */
  async loadVendors() {
    try {
      const response = await fetch(this.apiBase + "vendors.php")
      const data = await response.json()

      if (data.vendors) {
        this.vendors = data.vendors
        this.updateVendorSelects()
        this.updateVendorsList()
      }
    } catch (error) {
      console.error("Load vendors error:", error)
    }
  }

  /**
   * Update vendor select dropdowns
   */
  updateVendorSelects() {
    const vendorSelect = document.getElementById("vendorSelect")
    const vendorFilter = document.getElementById("vendorFilter")

    // Clear existing options (except first)
    vendorSelect.innerHTML = '<option value="">Select Vendor</option>'
    vendorFilter.innerHTML = '<option value="">All Vendors</option>'

    this.vendors.forEach((vendor) => {
      const option1 = new Option(vendor.vendor_name, vendor.id)
      const option2 = new Option(vendor.vendor_name, vendor.id)
      vendorSelect.appendChild(option1)
      vendorFilter.appendChild(option2)
    })
  }

  /**
   * Update vendors list in modal
   */
  updateVendorsList() {
    const vendorsList = document.getElementById("vendorsList")

    if (this.vendors.length === 0) {
      vendorsList.innerHTML = '<p class="no-vendors">No vendors added yet.</p>'
      return
    }

    vendorsList.innerHTML = this.vendors
      .map(
        (vendor) => `
      <div class="vendor-item">
        <div class="vendor-info">
          <strong>${this.escapeHtml(vendor.vendor_name)}</strong>
          ${vendor.contact_info ? `<br><small>${this.escapeHtml(vendor.contact_info)}</small>` : ""}
          <br><small>${vendor.product_count} products</small>
        </div>
        <div class="vendor-actions">
          <button class="btn btn-small btn-secondary" onclick="enhancedPricingTracker.editVendor(${vendor.id})">
            Edit
          </button>
          <button class="btn btn-small btn-danger" onclick="enhancedPricingTracker.deleteVendor(${vendor.id})">
            Delete
          </button>
        </div>
      </div>
    `,
      )
      .join("")
  }

  /**
   * Load products with current filters
   */
  async loadProducts() {
    this.showLoading(true)

    try {
      // Build query parameters
      const params = new URLSearchParams()
      Object.keys(this.filters).forEach((key) => {
        if (this.filters[key]) {
          params.append(key.replace(/([A-Z])/g, "_$1").toLowerCase(), this.filters[key])
        }
      })

      const response = await fetch(this.apiBase + "products.php?" + params.toString())
      const data = await response.json()

      if (data.products) {
        this.products = data.products
        this.filteredProducts = [...this.products]
        this.renderProducts()
        this.loadDashboardData() // Refresh dashboard stats
      } else {
        this.showToast("Failed to load products", "error")
      }
    } catch (error) {
      console.error("Load products error:", error)
      this.showToast("Network error loading products", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Render products in the enhanced table
   */
  renderProducts() {
    const tbody = document.getElementById("productsTableBody")
    const emptyState = document.getElementById("emptyState")

    if (this.filteredProducts.length === 0) {
      tbody.innerHTML = ""
      emptyState.classList.remove("hidden")
      return
    }

    emptyState.classList.add("hidden")

    tbody.innerHTML = this.filteredProducts
      .map(
        (product) => `
        <tr class="product-row ${product.status}">
          <td class="image-cell">
            ${
              product.product_image
                ? `<img src="${this.escapeHtml(product.product_image)}" alt="Product" class="product-thumbnail" onerror="this.style.display='none'">`
                : '<div class="no-image">📷</div>'
            }
          </td>
          <td class="product-name-cell">
            <strong>${this.escapeHtml(product.product_name)}</strong>
            ${product.notes ? `<br><small class="product-notes">${this.escapeHtml(product.notes)}</small>` : ""}
          </td>
          <td class="vendor-cell">
            ${
              product.vendor_name
                ? `<span class="vendor-tag">${this.escapeHtml(product.vendor_name)}</span>`
                : '<span class="no-vendor">No Vendor</span>'
            }
          </td>
          <td class="price-cell">GHS ${Number.parseFloat(product.actual_price).toFixed(2)}</td>
          <td class="markup-cell">${Number.parseFloat(product.markup_percentage).toFixed(1)}%</td>
          <td class="price-cell">GHS ${Number.parseFloat(product.selling_price).toFixed(2)}</td>
          <td class="quantity-cell">
            <span class="quantity-badge">${product.quantity}</span>
          </td>
          <td class="profit-cell">
            <strong class="profit-amount">GHS ${Number.parseFloat(product.total_profit).toFixed(2)}</strong>
          </td>
          <td class="status-cell">
            <span class="status-badge status-${product.status}">${this.capitalizeFirst(product.status)}</span>
          </td>
          <td class="date-cell">${this.formatDate(product.date_added)}</td>
          <td class="actions-cell">
            ${
              product.product_url
                ? `<a href="${this.escapeHtml(product.product_url)}" target="_blank" class="btn btn-small btn-link" title="View Product">🔗</a>`
                : ""
            }
            <button class="btn btn-small btn-secondary" onclick="enhancedPricingTracker.editProduct(${product.id})" title="Edit">
              ✏️
            </button>
            <button class="btn btn-small btn-success" onclick="enhancedPricingTracker.recordSale(${product.id})" title="Record Sale">
              💰
            </button>
            <button class="btn btn-small btn-danger" onclick="enhancedPricingTracker.deleteProduct(${product.id})" title="Delete">
              🗑️
            </button>
          </td>
        </tr>
      `,
      )
      .join("")
  }

  /**
   * Handle search functionality
   */
  handleSearch(query) {
    this.filters.search = query.trim()
    this.debounceLoadProducts()
  }

  /**
   * Handle filter changes
   */
  handleFilter(filterType, value) {
    this.filters[filterType] = value
    this.debounceLoadProducts()
  }

  /**
   * Handle sorting
   */
  handleSort(sortBy) {
    const [field, direction] = sortBy.split("_")
    const isAsc = direction === "asc"

    this.filteredProducts.sort((a, b) => {
      let aVal = a[field]
      let bVal = b[field]

      // Handle different data types
      if (field === "product_name") {
        aVal = aVal.toLowerCase()
        bVal = bVal.toLowerCase()
      } else if (
        ["actual_price", "markup_percentage", "selling_price", "profit", "total_profit", "quantity"].includes(field)
      ) {
        aVal = Number.parseFloat(aVal)
        bVal = Number.parseFloat(bVal)
      } else if (field === "date_added" || field === "created_at") {
        aVal = new Date(aVal)
        bVal = new Date(bVal)
      }

      if (aVal < bVal) return isAsc ? -1 : 1
      if (aVal > bVal) return isAsc ? 1 : -1
      return 0
    })

    this.renderProducts()
  }

  /**
   * Clear all filters
   */
  clearAllFilters() {
    this.filters = {
      search: "",
      vendor: "",
      status: "",
      minPrice: "",
      maxPrice: "",
      minProfit: "",
      maxProfit: "",
      dateFrom: "",
      dateTo: "",
    }

    // Reset form elements
    document.getElementById("searchInput").value = ""
    document.getElementById("vendorFilter").value = ""
    document.getElementById("statusFilter").value = ""
    document.getElementById("minPrice").value = ""
    document.getElementById("maxPrice").value = ""
    document.getElementById("minProfit").value = ""
    document.getElementById("maxProfit").value = ""
    document.getElementById("dateFrom").value = ""
    document.getElementById("dateTo").value = ""

    this.loadProducts()
  }

  /**
   * Debounced product loading for filters
   */
  debounceLoadProducts() {
    clearTimeout(this.loadProductsTimeout)
    this.loadProductsTimeout = setTimeout(() => {
      this.loadProducts()
    }, 300)
  }

  /**
   * Show enhanced product modal
   */
  showProductModal(product = null) {
    const modal = document.getElementById("productModal")
    const title = document.getElementById("modalTitle")
    const form = document.getElementById("productForm")

    if (product) {
      // Edit mode
      title.textContent = "Edit Product"
      this.currentEditId = product.id

      // Populate form fields
      document.getElementById("productId").value = product.id
      document.getElementById("productName").value = product.product_name
      document.getElementById("vendorSelect").value = product.vendor_id || ""
      document.getElementById("actualPrice").value = Number.parseFloat(product.actual_price).toFixed(2)
      document.getElementById("markupPercentage").value = Number.parseFloat(product.markup_percentage).toFixed(2)
      document.getElementById("quantity").value = product.quantity
      document.getElementById("productUrl").value = product.product_url || ""
      document.getElementById("productImage").value = product.product_image || ""
      document.getElementById("notes").value = product.notes || ""
      document.getElementById("dateAdded").value = product.date_added
      document.getElementById("status").value = product.status

      this.calculatePricing()
    } else {
      // Add mode
      title.textContent = "Add New Product"
      this.currentEditId = null
      form.reset()
      document.getElementById("quantity").value = 1
      document.getElementById("dateAdded").value = new Date().toISOString().split("T")[0]
      document.getElementById("status").value = "active"
      document.getElementById("sellingPrice").value = ""
      document.getElementById("totalProfit").value = ""
    }

    modal.classList.remove("hidden")
    document.getElementById("productName").focus()
  }

  /**
   * Hide product modal
   */
  hideProductModal() {
    document.getElementById("productModal").classList.add("hidden")
    document.getElementById("productForm").reset()
    this.currentEditId = null
  }

  /**
   * Calculate enhanced pricing with quantity
   */
  calculatePricing() {
    const actualPrice = Number.parseFloat(document.getElementById("actualPrice").value) || 0
    const markupPercentage = Number.parseFloat(document.getElementById("markupPercentage").value) || 0
    const quantity = Number.parseInt(document.getElementById("quantity").value) || 1

    const sellingPrice = actualPrice * (1 + markupPercentage / 100)
    const profit = sellingPrice - actualPrice
    const totalProfit = profit * quantity

    document.getElementById("sellingPrice").value = sellingPrice.toFixed(2)
    document.getElementById("totalProfit").value = totalProfit.toFixed(2)
  }

  /**
   * Setup markup presets
   */
  setupMarkupPresets() {
    // Markup presets are already handled in bindEvents
    // This method can be extended to load custom presets from API
  }

  /**
   * Handle enhanced product form submission
   */
  async handleProductSubmit(e) {
    e.preventDefault()

    const formData = new FormData(e.target)
    const productData = {
      product_name: formData.get("product_name").trim(),
      vendor_id: formData.get("vendor_id") || null,
      actual_price: Number.parseFloat(formData.get("actual_price")),
      markup_percentage: Number.parseFloat(formData.get("markup_percentage")),
      quantity: Number.parseInt(formData.get("quantity")) || 1,
      product_url: formData.get("product_url").trim() || null,
      product_image: formData.get("product_image").trim() || null,
      notes: formData.get("notes").trim() || null,
      date_added: formData.get("date_added"),
      status: formData.get("status"),
    }

    // Enhanced validation
    if (!productData.product_name) {
      this.showToast("Product name is required", "error")
      return
    }

    if (productData.actual_price <= 0) {
      this.showToast("Actual price must be greater than 0", "error")
      return
    }

    if (productData.markup_percentage < 0) {
      this.showToast("Markup percentage cannot be negative", "error")
      return
    }

    if (productData.quantity < 1) {
      this.showToast("Quantity must be at least 1", "error")
      return
    }

    this.showLoading(true)

    try {
      const url = this.apiBase + "products.php"
      let method = "POST"

      if (this.currentEditId) {
        productData.id = this.currentEditId
        method = "PUT"
      }

      const response = await fetch(url, {
        method: method,
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(productData),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(data.message, "success")
        this.hideProductModal()
        await this.loadProducts()
      } else {
        this.showToast(data.error || "Operation failed", "error")
      }
    } catch (error) {
      console.error("Product save error:", error)
      this.showToast("Network error. Please try again.", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Edit a product
   */
  editProduct(id) {
    const product = this.products.find((p) => p.id == id)
    if (product) {
      this.showProductModal(product)
    }
  }

  /**
   * Mark product as sold
   */
  async markAsSold(id) {
    const product = this.products.find((p) => p.id == id)
    if (!product) return

    if (!confirm(`Mark "${product.product_name}" as sold?`)) {
      return
    }

    this.showLoading(true)

    try {
      const response = await fetch(this.apiBase + "products.php", {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id: id,
          ...product,
          status: "sold",
        }),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast("Product marked as sold!", "success")
        await this.loadProducts()
      } else {
        this.showToast(data.error || "Update failed", "error")
      }
    } catch (error) {
      console.error("Mark as sold error:", error)
      this.showToast("Network error. Please try again.", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Delete a product
   */
  async deleteProduct(id) {
    const product = this.products.find((p) => p.id == id)
    if (!product) return

    if (!confirm(`Are you sure you want to delete "${product.product_name}"?`)) {
      return
    }

    this.showLoading(true)

    try {
      const response = await fetch(this.apiBase + "products.php", {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id }),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(data.message, "success")
        await this.loadProducts()
      } else {
        this.showToast(data.error || "Delete failed", "error")
      }
    } catch (error) {
      console.error("Delete error:", error)
      this.showToast("Network error. Please try again.", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Show vendor management modal
   */
  showVendorModal() {
    document.getElementById("vendorModal").classList.remove("hidden")
    this.loadVendors()
  }

  /**
   * Hide vendor modal
   */
  hideVendorModal() {
    document.getElementById("vendorModal").classList.add("hidden")
    document.getElementById("vendorForm").reset()
    this.currentVendorEditId = null
  }

  /**
   * Handle vendor form submission
   */
  async handleVendorSubmit(e) {
    e.preventDefault()

    const vendorName = document.getElementById("vendorName").value.trim()
    const contactInfo = document.getElementById("contactInfo").value.trim()

    if (!vendorName) {
      this.showToast("Vendor name is required", "error")
      return
    }

    const vendorData = {
      vendor_name: vendorName,
      contact_info: contactInfo || null,
    }

    let method = "POST"
    if (this.currentVendorEditId) {
      vendorData.id = this.currentVendorEditId
      method = "PUT"
    }

    try {
      const response = await fetch(this.apiBase + "vendors.php", {
        method: method,
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(vendorData),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(data.message, "success")
        document.getElementById("vendorForm").reset()
        this.currentVendorEditId = null
        await this.loadVendors()
      } else {
        this.showToast(data.error || "Operation failed", "error")
      }
    } catch (error) {
      console.error("Vendor save error:", error)
      this.showToast("Network error. Please try again.", "error")
    }
  }

  /**
   * Edit vendor
   */
  editVendor(id) {
    const vendor = this.vendors.find((v) => v.id == id)
    if (vendor) {
      this.currentVendorEditId = id
      document.getElementById("vendorId").value = id
      document.getElementById("vendorName").value = vendor.vendor_name
      document.getElementById("contactInfo").value = vendor.contact_info || ""
    }
  }

  /**
   * Delete vendor
   */
  async deleteVendor(id) {
    const vendor = this.vendors.find((v) => v.id == id)
    if (!vendor) return

    if (!confirm(`Delete vendor "${vendor.vendor_name}"? This will remove the vendor from all products.`)) {
      return
    }

    try {
      const response = await fetch(this.apiBase + "vendors.php", {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id }),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(data.message, "success")
        await this.loadVendors()
        await this.loadProducts() // Refresh products to update vendor info
      } else {
        this.showToast(data.error || "Delete failed", "error")
      }
    } catch (error) {
      console.error("Delete vendor error:", error)
      this.showToast("Network error. Please try again.", "error")
    }
  }

  /**
   * Export products to CSV
   */
  async exportProducts() {
    try {
      const response = await fetch(this.apiBase + "import-export.php?action=export")

      if (response.ok) {
        const blob = await response.blob()
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement("a")
        a.href = url
        a.download = `products_export_${new Date().toISOString().split("T")[0]}.csv`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        window.URL.revokeObjectURL(url)

        this.showToast("Products exported successfully!", "success")
      } else {
        this.showToast("Export failed", "error")
      }
    } catch (error) {
      console.error("Export error:", error)
      this.showToast("Export failed", "error")
    }
  }

  /**
   * Show import modal
   */
  showImportModal() {
    document.getElementById("importModal").classList.remove("hidden")
  }

  /**
   * Hide import modal
   */
  hideImportModal() {
    document.getElementById("importModal").classList.add("hidden")
    document.getElementById("importForm").reset()
    document.getElementById("importResults").classList.add("hidden")
  }

  /**
   * Download CSV template
   */
  async downloadTemplate() {
    try {
      const response = await fetch(this.apiBase + "import-export.php?action=template")

      if (response.ok) {
        const blob = await response.blob()
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement("a")
        a.href = url
        a.download = "import_template.csv"
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        window.URL.revokeObjectURL(url)

        this.showToast("Template downloaded!", "success")
      } else {
        this.showToast("Download failed", "error")
      }
    } catch (error) {
      console.error("Template download error:", error)
      this.showToast("Download failed", "error")
    }
  }

  /**
   * Handle import form submission
   */
  async handleImportSubmit(e) {
    e.preventDefault()

    const fileInput = document.getElementById("csvFile")
    const file = fileInput.files[0]

    if (!file) {
      this.showToast("Please select a CSV file", "error")
      return
    }

    this.showLoading(true)

    try {
      const formData = new FormData()
      formData.append("csv_file", file)

      const response = await fetch(this.apiBase + "import-export.php?action=import", {
        method: "POST",
        body: formData,
      })

      const data = await response.json()

      const resultsDiv = document.getElementById("importResults")
      resultsDiv.classList.remove("hidden")

      if (data.success) {
        resultsDiv.innerHTML = `
          <div class="import-success">
            <h4>✅ Import Successful!</h4>
            <p>${data.message}</p>
            <p><strong>Imported:</strong> ${data.imported_count} products</p>
            ${
              data.errors.length > 0
                ? `
              <details>
                <summary>Errors (${data.errors.length})</summary>
                <ul>
                  ${data.errors.map((error) => `<li>${error}</li>`).join("")}
                </ul>
              </details>
            `
                : ""
            }
          </div>
        `

        this.showToast(`${data.imported_count} products imported successfully!`, "success")
        await this.loadProducts()
      } else {
        resultsDiv.innerHTML = `
          <div class="import-error">
            <h4>❌ Import Failed</h4>
            <p>${data.error}</p>
          </div>
        `
        this.showToast(data.error, "error")
      }
    } catch (error) {
      console.error("Import error:", error)
      this.showToast("Import failed", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Logout user
   */
  async logout() {
    try {
      await fetch(this.apiBase + "auth.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ action: "logout" }),
      })
    } catch (error) {
      console.error("Logout error:", error)
    }

    window.location.href = "index.html"
  }

  /**
   * Show/hide loading overlay
   */
  showLoading(show) {
    const overlay = document.getElementById("loadingOverlay")
    if (show) {
      overlay.classList.remove("hidden")
    } else {
      overlay.classList.add("hidden")
    }
  }

  /**
   * Show enhanced toast notification
   */
  showToast(message, type = "info") {
    const toast = document.getElementById("toast")
    const messageEl = document.getElementById("toastMessage")

    messageEl.textContent = message
    toast.className = `toast ${type}`
    toast.classList.remove("hidden")

    setTimeout(() => {
      toast.classList.add("hidden")
    }, 4000)
  }

  /**
   * Utility functions
   */
  escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }

  capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1)
  }

  formatDate(dateString) {
    const date = new Date(dateString)
    return date.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    })
  }

  /**
   * Record a sale for a product
   */
  recordSale(productId) {
    const product = this.products.find((p) => p.id == productId)
    if (!product) return

    this.showSalesModal(product)
  }

  /**
   * Show sales modal
   */
  showSalesModal(product) {
    const modal = document.getElementById("salesModal")
    const form = document.getElementById("salesForm")

    // Reset form
    form.reset()
    document.getElementById("saleProductId").value = product.id
    document.getElementById("saleDate").value = new Date().toISOString().split("T")[0]

    // Display product info
    const productInfo = document.getElementById("productInfo")
    productInfo.innerHTML = `
      <div class="product-info-card">
        <h5>${this.escapeHtml(product.product_name)}</h5>
        <p><strong>Current Price:</strong> GHS ${Number.parseFloat(product.actual_price).toFixed(2)}</p>
        <p><strong>Suggested Sale Price:</strong> GHS ${Number.parseFloat(product.selling_price).toFixed(2)}</p>
        <p><strong>Available Quantity:</strong> ${product.quantity}</p>
      </div>
    `

    // Set default values
    document.getElementById("quantitySold").value = 1
    document.getElementById("quantitySold").max = product.quantity
    document.getElementById("salePrice").value = Number.parseFloat(product.selling_price).toFixed(2)

    this.calculateSaleProfit()

    modal.classList.remove("hidden")
    document.getElementById("quantitySold").focus()
  }

  /**
   * Hide sales modal
   */
  hideSalesModal() {
    document.getElementById("salesModal").classList.add("hidden")
    document.getElementById("salesForm").reset()
  }

  /**
   * Calculate sale profit
   */
  calculateSaleProfit() {
    const productId = document.getElementById("saleProductId").value
    const product = this.products.find((p) => p.id == productId)

    if (!product) return

    const quantitySold = Number.parseInt(document.getElementById("quantitySold").value) || 0
    const salePrice = Number.parseFloat(document.getElementById("salePrice").value) || 0

    const actualProfit = (salePrice - product.actual_price) * quantitySold
    document.getElementById("actualProfit").value = actualProfit.toFixed(2)
  }

  /**
   * Handle sales form submission
   */
  async handleSalesSubmit(e) {
    e.preventDefault()

    const formData = new FormData(e.target)
    const saleData = {
      product_id: document.getElementById("saleProductId").value,
      quantity_sold: Number.parseInt(formData.get("quantity_sold")),
      sale_price: Number.parseFloat(formData.get("sale_price")),
      sale_date: formData.get("sale_date"),
      notes: formData.get("notes") || null,
      update_inventory: document.getElementById("updateInventory").checked,
    }

    // Validation
    if (saleData.quantity_sold <= 0) {
      this.showToast("Quantity sold must be greater than 0", "error")
      return
    }

    if (saleData.sale_price <= 0) {
      this.showToast("Sale price must be greater than 0", "error")
      return
    }

    this.showLoading(true)

    try {
      const response = await fetch(this.apiBase + "sales.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(saleData),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(`Sale recorded! Profit: GHS ${data.actual_profit.toFixed(2)}`, "success")
        this.hideSalesModal()
        await this.loadProducts()
        await this.loadDashboardData()
      } else {
        this.showToast(data.error || "Failed to record sale", "error")
      }
    } catch (error) {
      console.error("Record sale error:", error)
      this.showToast("Network error. Please try again.", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Show sales history modal
   */
  async showSalesHistoryModal() {
    const modal = document.getElementById("salesHistoryModal")
    modal.classList.remove("hidden")

    await this.loadSalesHistory()
  }

  /**
   * Hide sales history modal
   */
  hideSalesHistoryModal() {
    document.getElementById("salesHistoryModal").classList.add("hidden")
  }

  /**
   * Load sales history and statistics
   */
  async loadSalesHistory() {
    this.showLoading(true)

    try {
      // Load sales data
      const salesResponse = await fetch(this.apiBase + "sales.php")
      const salesData = await salesResponse.json()

      // Load sales statistics
      const statsResponse = await fetch(this.apiBase + "sales.php?action=stats")
      const statsData = await statsResponse.json()

      if (salesData.sales && statsData.overall_stats) {
        this.renderSalesHistory(salesData.sales)
        this.renderSalesStats(statsData.overall_stats)
      }
    } catch (error) {
      console.error("Load sales history error:", error)
      this.showToast("Failed to load sales history", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Render sales statistics
   */
  renderSalesStats(stats) {
    document.getElementById("totalSalesCount").textContent = stats.total_sales || 0
    document.getElementById("totalItemsSold").textContent = stats.total_items_sold || 0
    document.getElementById("totalSalesProfit").textContent = `GHS ${(stats.total_profit || 0).toFixed(2)}`
    document.getElementById("avgProfitPerSale").textContent = `GHS ${(stats.avg_profit_per_sale || 0).toFixed(2)}`
  }

  /**
   * Render sales history table
   */
  renderSalesHistory(sales) {
    const tbody = document.getElementById("salesTableBody")
    const emptyState = document.getElementById("emptySalesState")

    if (sales.length === 0) {
      tbody.innerHTML = ""
      emptyState.classList.remove("hidden")
      return
    }

    emptyState.classList.add("hidden")

    tbody.innerHTML = sales
      .map(
        (sale) => `
        <tr>
          <td>${this.formatDate(sale.sale_date)}</td>
          <td><strong>${this.escapeHtml(sale.product_name)}</strong></td>
          <td>${sale.vendor_name ? this.escapeHtml(sale.vendor_name) : "No Vendor"}</td>
          <td class="text-center">${sale.quantity_sold}</td>
          <td class="text-right">GHS ${Number.parseFloat(sale.sale_price).toFixed(2)}</td>
          <td class="text-right profit-amount">GHS ${Number.parseFloat(sale.actual_profit).toFixed(2)}</td>
          <td class="actions-cell">
            <button class="btn btn-small btn-danger" onclick="enhancedPricingTracker.deleteSale(${sale.id})" title="Delete">
              🗑️
            </button>
          </td>
        </tr>
      `,
      )
      .join("")
  }

  /**
   * Delete a sale record
   */
  async deleteSale(saleId) {
    if (!confirm("Are you sure you want to delete this sale record?")) {
      return
    }

    this.showLoading(true)

    try {
      const response = await fetch(this.apiBase + "sales.php", {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: saleId }),
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(data.message, "success")
        await this.loadSalesHistory()
        await this.loadDashboardData()
      } else {
        this.showToast(data.error || "Delete failed", "error")
      }
    } catch (error) {
      console.error("Delete sale error:", error)
      this.showToast("Network error. Please try again.", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Show backup modal
   */
  async showBackupModal() {
    const modal = document.getElementById("backupModal")
    modal.classList.remove("hidden")

    await this.loadBackupsList()
  }

  /**
   * Hide backup modal
   */
  hideBackupModal() {
    document.getElementById("backupModal").classList.add("hidden")
  }

  /**
   * Create backup
   */
  async createBackup() {
    this.showLoading(true)

    try {
      const response = await fetch(this.apiBase + "backup.php?action=backup")
      const data = await response.json()

      if (data.success) {
        this.showToast(
          `Backup created successfully! ${data.records.products} products, ${data.records.vendors} vendors, ${data.records.sales} sales`,
          "success",
        )
        await this.loadBackupsList()
      } else {
        this.showToast(data.error || "Backup failed", "error")
      }
    } catch (error) {
      console.error("Create backup error:", error)
      this.showToast("Backup creation failed", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Load backups list
   */
  async loadBackupsList() {
    try {
      const response = await fetch(this.apiBase + "backup.php?action=list")
      const data = await response.json()

      const backupsList = document.getElementById("backupsList")

      if (data.backups && data.backups.length > 0) {
        backupsList.innerHTML = data.backups
          .map(
            (backup) => `
            <div class="backup-item">
              <div class="backup-info">
                <strong>${backup.filename}</strong>
                <br><small>Created: ${backup.created}</small>
                <br><small>Size: ${this.formatFileSize(backup.size)}</small>
                ${
                  backup.info
                    ? `<br><small>${backup.info.products} products, ${backup.info.vendors} vendors, ${backup.info.sales} sales</small>`
                    : ""
                }
              </div>
              <div class="backup-actions">
                <button class="btn btn-small btn-danger" onclick="enhancedPricingTracker.deleteBackup('${backup.filename}')">
                  Delete
                </button>
              </div>
            </div>
          `,
          )
          .join("")
      } else {
        backupsList.innerHTML = '<p class="no-backups">No backups found.</p>'
      }
    } catch (error) {
      console.error("Load backups error:", error)
      document.getElementById("backupsList").innerHTML = '<p class="error">Failed to load backups.</p>'
    }
  }

  /**
   * Delete backup
   */
  async deleteBackup(filename) {
    if (!confirm(`Delete backup "${filename}"?`)) {
      return
    }

    try {
      const formData = new FormData()
      formData.append("filename", filename)

      const response = await fetch(this.apiBase + "backup.php?action=delete", {
        method: "POST",
        body: formData,
      })

      const data = await response.json()

      if (data.success) {
        this.showToast(data.message, "success")
        await this.loadBackupsList()
      } else {
        this.showToast(data.error || "Delete failed", "error")
      }
    } catch (error) {
      console.error("Delete backup error:", error)
      this.showToast("Failed to delete backup", "error")
    }
  }

  /**
   * Handle restore form submission
   */
  async handleRestoreSubmit(e) {
    e.preventDefault()

    const fileInput = document.getElementById("backupFile")
    const file = fileInput.files[0]

    if (!file) {
      this.showToast("Please select a backup file", "error")
      return
    }

    if (!confirm("This will restore data from the backup file. Continue?")) {
      return
    }

    this.showLoading(true)

    try {
      const formData = new FormData()
      formData.append("backup_file", file)
      formData.append("clear_existing", document.getElementById("clearExisting").checked ? "true" : "false")

      const response = await fetch(this.apiBase + "backup.php?action=restore", {
        method: "POST",
        body: formData,
      })

      const data = await response.json()

      const resultsDiv = document.getElementById("backupResults")
      resultsDiv.classList.remove("hidden")

      if (data.success) {
        resultsDiv.innerHTML = `
          <div class="restore-success">
            <h4>✅ Restore Successful!</h4>
            <p>${data.message}</p>
            <ul>
              <li><strong>Products:</strong> ${data.restored.products}</li>
              <li><strong>Vendors:</strong> ${data.restored.vendors}</li>
              <li><strong>Sales:</strong> ${data.restored.sales}</li>
              <li><strong>Markup Presets:</strong> ${data.restored.markup_presets}</li>
            </ul>
          </div>
        `

        this.showToast("Data restored successfully!", "success")
        await this.loadProducts()
        await this.loadVendors()
        await this.loadDashboardData()
      } else {
        resultsDiv.innerHTML = `
          <div class="restore-error">
            <h4>❌ Restore Failed</h4>
            <p>${data.error}</p>
          </div>
        `
        this.showToast(data.error, "error")
      }
    } catch (error) {
      console.error("Restore error:", error)
      this.showToast("Restore failed", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Show image upload modal
   */
  showImageUploadModal() {
    document.getElementById("imageUploadModal").classList.remove("hidden")
  }

  /**
   * Hide image upload modal
   */
  hideImageUploadModal() {
    document.getElementById("imageUploadModal").classList.add("hidden")
    document.getElementById("imageUploadForm").reset()
    document.getElementById("imagePreview").style.display = "none"
    document.getElementById("imageUploadResults").classList.add("hidden")
  }

  /**
   * Preview selected image
   */
  previewImage(e) {
    const file = e.target.files[0]
    if (file) {
      const reader = new FileReader()
      reader.onload = (e) => {
        const preview = document.getElementById("imagePreview")
        const img = document.getElementById("previewImage")
        img.src = e.target.result
        preview.style.display = "block"
      }
      reader.readAsDataURL(file)
    }
  }

  /**
   * Handle image upload
   */
  async handleImageUpload(e) {
    e.preventDefault()

    const fileInput = document.getElementById("productImageFile")
    const file = fileInput.files[0]

    if (!file) {
      this.showToast("Please select an image file", "error")
      return
    }

    this.showLoading(true)

    try {
      const formData = new FormData()
      formData.append("image", file)

      const response = await fetch(this.apiBase + "upload.php", {
        method: "POST",
        body: formData,
      })

      const data = await response.json()

      const resultsDiv = document.getElementById("imageUploadResults")
      resultsDiv.classList.remove("hidden")

      if (data.success) {
        resultsDiv.innerHTML = `
          <div class="upload-success">
            <h4>✅ Upload Successful!</h4>
            <p>${data.message}</p>
            <img src="${data.thumbnail_url}" alt="Uploaded image" style="max-width: 100px; max-height: 100px;">
          </div>
        `

        // Set the image URL in the product form
        document.getElementById("productImage").value = data.image_url

        // Update current image preview
        const currentPreview = document.getElementById("currentImagePreview")
        currentPreview.innerHTML = `
          <div class="current-image">
            <img src="${data.thumbnail_url}" alt="Current product image" style="max-width: 100px; max-height: 100px;">
            <p><small>Current image</small></p>
          </div>
        `

        this.showToast("Image uploaded successfully!", "success")
        setTimeout(() => {
          this.hideImageUploadModal()
        }, 2000)
      } else {
        resultsDiv.innerHTML = `
          <div class="upload-error">
            <h4>❌ Upload Failed</h4>
            <p>${data.error}</p>
          </div>
        `
        this.showToast(data.error, "error")
      }
    } catch (error) {
      console.error("Image upload error:", error)
      this.showToast("Upload failed", "error")
    } finally {
      this.showLoading(false)
    }
  }

  /**
   * Update the renderProducts method to include the new sales button
   */
  renderProducts() {
    const tbody = document.getElementById("productsTableBody")
    const emptyState = document.getElementById("emptyState")

    if (this.filteredProducts.length === 0) {
      tbody.innerHTML = ""
      emptyState.classList.remove("hidden")
      return
    }

    emptyState.classList.add("hidden")

    tbody.innerHTML = this.filteredProducts
      .map(
        (product) => `
        <tr class="product-row ${product.status}">
          <td class="image-cell">
            ${
              product.product_image
                ? `<img src="${this.escapeHtml(product.product_image)}" alt="Product" class="product-thumbnail" onerror="this.style.display='none'">`
                : '<div class="no-image">📷</div>'
            }
          </td>
          <td class="product-name-cell">
            <strong>${this.escapeHtml(product.product_name)}</strong>
            ${product.notes ? `<br><small class="product-notes">${this.escapeHtml(product.notes)}</small>` : ""}
          </td>
          <td class="vendor-cell">
            ${
              product.vendor_name
                ? `<span class="vendor-tag">${this.escapeHtml(product.vendor_name)}</span>`
                : '<span class="no-vendor">No Vendor</span>'
            }
          </td>
          <td class="price-cell">GHS ${Number.parseFloat(product.actual_price).toFixed(2)}</td>
          <td class="markup-cell">${Number.parseFloat(product.markup_percentage).toFixed(1)}%</td>
          <td class="price-cell">GHS ${Number.parseFloat(product.selling_price).toFixed(2)}</td>
          <td class="quantity-cell">
            <span class="quantity-badge">${product.quantity}</span>
          </td>
          <td class="profit-cell">
            <strong class="profit-amount">GHS ${Number.parseFloat(product.total_profit).toFixed(2)}</strong>
          </td>
          <td class="status-cell">
            <span class="status-badge status-${product.status}">${this.capitalizeFirst(product.status)}</span>
          </td>
          <td class="date-cell">${this.formatDate(product.date_added)}</td>
          <td class="actions-cell">
            ${
              product.product_url
                ? `<a href="${this.escapeHtml(product.product_url)}" target="_blank" class="btn btn-small btn-link" title="View Product">🔗</a>`
                : ""
            }
            <button class="btn btn-small btn-secondary" onclick="enhancedPricingTracker.editProduct(${product.id})" title="Edit">
              ✏️
            </button>
            <button class="btn btn-small btn-success" onclick="enhancedPricingTracker.recordSale(${product.id})" title="Record Sale">
              💰
            </button>
            <button class="btn btn-small btn-danger" onclick="enhancedPricingTracker.deleteProduct(${product.id})" title="Delete">
              🗑️
            </button>
          </td>
        </tr>
      `,
      )
      .join("")
  }

  /**
   * Format file size for display
   */
  formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes"
    const k = 1024
    const sizes = ["Bytes", "KB", "MB", "GB"]
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i]
  }
}

// Initialize the enhanced application when DOM is loaded
let enhancedPricingTracker
document.addEventListener("DOMContentLoaded", () => {
  enhancedPricingTracker = new EnhancedPricingTracker()
})
