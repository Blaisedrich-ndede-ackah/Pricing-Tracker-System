/**
 * Pricing Tracker Dashboard JavaScript
 * Handles all frontend functionality for product management
 */

class PricingTracker {
  constructor() {
    this.apiBase = "api/"
    this.products = []
    this.filteredProducts = []
    this.currentEditId = null

    this.init()
  }

  /**
   * Initialize the application
   */
  init() {
    this.checkAuthentication()
    this.bindEvents()
    this.loadProducts()
  }

  /**
   * Check if user is authenticated, redirect to login if not
   */
  async checkAuthentication() {
    try {
      const response = await fetch(this.apiBase + "auth.php")
      const data = await response.json()

      if (!data.authenticated) {
        window.location.href = "index.html"
        return
      }
    } catch (error) {
      console.error("Auth check failed:", error)
      window.location.href = "index.html"
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
    document.getElementById("searchInput").addEventListener("input", (e) => this.handleSearch(e.target.value))
    document.getElementById("sortSelect").addEventListener("change", (e) => this.handleSort(e.target.value))

    // Modal controls
    document.getElementById("closeModal").addEventListener("click", () => this.hideProductModal())
    document.getElementById("cancelBtn").addEventListener("click", () => this.hideProductModal())
    document.getElementById("productForm").addEventListener("submit", (e) => this.handleProductSubmit(e))

    // Auto-calculate selling price and profit
    document.getElementById("actualPrice").addEventListener("input", () => this.calculatePricing())
    document.getElementById("markupPercentage").addEventListener("input", () => this.calculatePricing())

    // Close modal when clicking outside
    document.getElementById("productModal").addEventListener("click", (e) => {
      if (e.target.id === "productModal") {
        this.hideProductModal()
      }
    })
  }

  /**
   * Load all products from the API
   */
  async loadProducts() {
    this.showLoading(true)

    try {
      const response = await fetch(this.apiBase + "products.php")
      const data = await response.json()

      if (data.products) {
        this.products = data.products
        this.filteredProducts = [...this.products]
        this.renderProducts()
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
   * Render products in the table
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
            <tr>
                <td><strong>${this.escapeHtml(product.product_name)}</strong></td>
                <td>$${Number.parseFloat(product.actual_price).toFixed(2)}</td>
                <td>${Number.parseFloat(product.markup_percentage).toFixed(1)}%</td>
                <td>$${Number.parseFloat(product.selling_price).toFixed(2)}</td>
                <td>$${Number.parseFloat(product.profit).toFixed(2)}</td>
                <td class="product-url">
                    ${
                      product.product_url
                        ? `<a href="${this.escapeHtml(product.product_url)}" target="_blank" rel="noopener">
                            ${this.escapeHtml(product.product_url)}
                        </a>`
                        : '<span style="color: #999;">No URL</span>'
                    }
                </td>
                <td class="actions-cell">
                    <button class="btn btn-small btn-secondary" onclick="pricingTracker.editProduct(${product.id})">
                        Edit
                    </button>
                    <button class="btn btn-small btn-danger" onclick="pricingTracker.deleteProduct(${product.id})">
                        Delete
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
    const searchTerm = query.toLowerCase().trim()

    if (!searchTerm) {
      this.filteredProducts = [...this.products]
    } else {
      this.filteredProducts = this.products.filter(
        (product) =>
          product.product_name.toLowerCase().includes(searchTerm) ||
          (product.product_url && product.product_url.toLowerCase().includes(searchTerm)),
      )
    }

    this.renderProducts()
  }

  /**
   * Handle sorting functionality
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
      } else if (["actual_price", "markup_percentage", "selling_price", "profit"].includes(field)) {
        aVal = Number.parseFloat(aVal)
        bVal = Number.parseFloat(bVal)
      } else if (field === "created_at") {
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
   * Show the product modal for adding/editing
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
      document.getElementById("actualPrice").value = Number.parseFloat(product.actual_price).toFixed(2)
      document.getElementById("markupPercentage").value = Number.parseFloat(product.markup_percentage).toFixed(2)
      document.getElementById("productUrl").value = product.product_url || ""

      this.calculatePricing()
    } else {
      // Add mode
      title.textContent = "Add New Product"
      this.currentEditId = null
      form.reset()
      document.getElementById("sellingPrice").value = ""
      document.getElementById("profit").value = ""
    }

    modal.classList.remove("hidden")
    document.getElementById("productName").focus()
  }

  /**
   * Hide the product modal
   */
  hideProductModal() {
    document.getElementById("productModal").classList.add("hidden")
    document.getElementById("productForm").reset()
    this.currentEditId = null
  }

  /**
   * Calculate selling price and profit based on actual price and markup
   */
  calculatePricing() {
    const actualPrice = Number.parseFloat(document.getElementById("actualPrice").value) || 0
    const markupPercentage = Number.parseFloat(document.getElementById("markupPercentage").value) || 0

    const sellingPrice = actualPrice * (1 + markupPercentage / 100)
    const profit = sellingPrice - actualPrice

    document.getElementById("sellingPrice").value = sellingPrice.toFixed(2)
    document.getElementById("profit").value = profit.toFixed(2)
  }

  /**
   * Handle product form submission
   */
  async handleProductSubmit(e) {
    e.preventDefault()

    const formData = new FormData(e.target)
    const productData = {
      product_name: formData.get("product_name").trim(),
      actual_price: Number.parseFloat(formData.get("actual_price")),
      markup_percentage: Number.parseFloat(formData.get("markup_percentage")),
      product_url: formData.get("product_url").trim() || null,
    }

    // Validation
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
   * Show toast notification
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
   * Escape HTML to prevent XSS
   */
  escapeHtml(text) {
    const div = document.createElement("div")
    div.textContent = text
    return div.innerHTML
  }
}

// Initialize the application when DOM is loaded
let pricingTracker
document.addEventListener("DOMContentLoaded", () => {
  pricingTracker = new PricingTracker()
})
