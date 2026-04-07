@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Gallon Management')
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/gallon.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/app.css') }}">
    <style>
        button.is-loading {
            pointer-events: none !important;
            cursor: not-allowed !important;
            opacity: 0.75 !important;
        }

        button.is-loading .btn-loading-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        button.is-loading .btn-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            display: inline-block;
            animation: btn-spin 0.7s linear infinite;
            vertical-align: middle;
        }

        @keyframes btn-spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    @section('content')
        @include('admin.partials.alert')
        <div class="content-area">

            <!-- PAGE HEADER -->
            <div class="gallon-header">
                <h2>Gallon list</h2>

                <div class="header-actions">
                    <div class="search-box">
                        <span class="material-symbols-outlined">search</span>

                        <input type="text" id="searchInput" placeholder="Search by flavor">

                        <button type="button" class="clear-search" id="clearSearch">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="filter-wrapper">
                        <button class="btn-filter" id="filterBtn">
                            <span class="material-symbols-outlined">filter_list</span>
                            Filter
                        </button>

                        <div class="filter-dropdown" id="filterDropdown">
                            <div class="filter-section">
                                <label>
                                    <input type="checkbox" data-filter="available">
                                    Available
                                </label>
                                <label>
                                    <input type="checkbox" data-filter="out">
                                    Out of Stock
                                </label>
                            </div>

                            <hr>

                            {{-- <div class="filter-section">
                                <label>
                                    <input type="checkbox" data-filter="ingredients">
                                    Ingredients
                                </label>
                                <label>
                                    <input type="checkbox" data-filter="flavor">
                                    Flavor
                                </label>
                            </div> --}}
                        </div>
                    </div>


                    <button class="btn-add">
                        <span class="material-symbols-outlined">add</span>
                        Add Gallon
                    </button>
                </div>
            </div>

            <!-- TABLE -->
            <div class="table-card">

                <!-- HEADER -->
                <div class="table-header gallon">
                    <div class="col check">
                        <input type="checkbox" id="selectAllGallons" aria-label="Select all gallons">
                        <button type="button" class="bulk-delete-btn" aria-label="Delete selected">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                    <div class="col">
                        Gallon Size
                    </div>
                    <div class="col col-sortable">
                        <a href="#" class="sort-link" data-sort-by="quantity" data-order="desc" aria-label="Sort by quantity">
                        
                            <span>Quantity</span>
                            <span class="sort-arrow" aria-hidden="true">
                                <svg class="sort-icon" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M6 1L2 5.5h8L6 1z" fill="currentColor"/>
                                    <path d="M6 13l4-4.5H2l4 4.5z" fill="currentColor"/>
                                </svg>
                            </span>
                        </a>
                    </div>
                    <div class="col col-sortable">
                        <a href="#" class="sort-link" data-sort-by="price" data-order="desc" aria-label="Sort by add-on price">
                           
                            <span>Add-on Price</span>
                            <span class="sort-arrow" aria-hidden="true">
                                <svg class="sort-icon" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M6 1L2 5.5h8L6 1z" fill="currentColor"/>
                                    <path d="M6 13l4-4.5H2l4 4.5z" fill="currentColor"/>
                                </svg>
                            </span>
                        </a>
                    </div>
                    <div class="col col-sortable">
                        <a href="#" class="sort-link" data-sort-by="status" data-order="asc" aria-label="Sort by status">
                           
                            <span>Status</span>
                            <span class="sort-arrow" aria-hidden="true">
                                <svg class="sort-icon" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M6 1L2 5.5h8L6 1z" fill="currentColor"/>
                                    <path d="M6 13l4-4.5H2l4 4.5z" fill="currentColor"/>
                                </svg>
                            </span>
                        </a>
                    </div>
                    <div class="col">
                         Action
                    </div>
                </div>


                <div class="table-body-container">

                    <div class="table-body-scroll">
                        <table class="flavor-table">
                            <colgroup>
                                <col style="width:6%">
                                <col style="width:24%">
                                <col style="width:15%">
                                <col style="width:17%">
                                <col style="width:18%">
                                <col style="width:20%">
                            </colgroup>

                            <tbody id="gallonTableBody">
                                @foreach ($gallons as $gallon)
                                    @php
                                        $gImage = data_get($gallon, 'image');
                                        $gStatus = data_get($gallon, 'status', 'out');
                                        $statusKey = $gStatus === 'available' ? 'available' : 'out';
                                    @endphp
                                    <tr data-size="{{ strtolower((string) data_get($gallon, 'size', '')) }}" data-quantity="{{ (int) data_get($gallon, 'quantity', 0) }}" data-price="{{ (float) data_get($gallon, 'addon_price', 0) }}" data-status="{{ $statusKey }}">

                                        <td>
                                            <input type="checkbox" class="row-select" value="{{ data_get($gallon, 'id') }}"
                                                data-name="{{ e((string) data_get($gallon, 'size', '')) }}">
                                        </td>

                                        <td class="flavor-col">
                                            <img src="{{ $gImage ? asset($gImage) : asset('img/gallon.png') }}"
                                                alt="Gallon {{ data_get($gallon, 'size', '') }}" loading="lazy">
                                            <strong>{{ data_get($gallon, 'size', '—') }}</strong>
                                        </td>


                                        <td>{{ data_get($gallon, 'quantity', 0) }}</td>

                                        <td>₱{{ number_format((float) data_get($gallon, 'addon_price', 0), 2) }}</td>

                                        <td>
                                            <span class="status {{ $gStatus }}">
                                                <span class="dot"></span>
                                                {{ $gStatus === 'available' ? 'Available' : 'Out of Stock' }}
                                            </span>
                                        </td>

                                        <td class="action">
                                            <button class="btn-edit" data-id="{{ data_get($gallon, 'id') }}"
                                                data-size="{{ data_get($gallon, 'size', '') }}" data-qty="{{ data_get($gallon, 'quantity', 0) }}"
                                                data-price="{{ data_get($gallon, 'addon_price', 0) }}"
                                                data-image="{{ $gImage ? asset($gImage) : '' }}"
                                                title="Edit" aria-label="Edit">
                                                <span class="material-symbols-outlined">edit_square</span>
                                            </button>


                                            <button class="btn-delete" data-id="{{ data_get($gallon, 'id') }}" title="Delete"
                                                aria-label="Delete">
                                                <span class="material-symbols-outlined">delete</span>
                                            </button>

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>



                        </table>
                    </div>

                    <div class="pagination-wrapper">
                        <button class="nav-btn" id="prevBtn">
                            <span class="material-symbols-outlined">arrow_left_alt</span>
                            Previous
                        </button>

                        <div id="pageNumbers"></div>
                        <div id="paginationInfo" class="page-num"></div>


                        <button class="nav-btn" id="nextBtn">
                            Next
                            <span class="material-symbols-outlined">arrow_right_alt</span>
                        </button>
                    </div>


                </div>

            </div>

        </div>

    @endsection

    <!-- ========================
        EDIT GALLON MODAL
    ======================== -->
    <div class="modal-overlay" id="editFlavorModal">
        <div class="modal-card edit-modal-card">

            <form id="editGallonForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h3>Edit Product</h3>
                    <button type="button" class="modal-close" id="closeEditModal">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="modal-body">

                    <!-- Upload -->
                    <div class="upload-box">
                        <div class="upload-preview" id="editImagePreview">
                            <span class="material-symbols-outlined">add_photo_alternate</span>
                        </div>

                        <input type="file" id="editFlavorImage" name="image" hidden>

                        <div class="upload-actions">
                            <label class="upload-label">Upload Image</label>
                            <button type="button" class="btn-upload"
                                onclick="document.getElementById('editFlavorImage').click()">
                                Upload
                            </button>
                        </div>
                    </div>

                    <!-- Gallon Size + Quantity -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gallon Size</label>
                            <input type="text" id="editGallonSize" name="size" required>
                        </div>

                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" id="editQuantity" name="quantity" required>
                        </div>
                    </div>

                    <!-- Add-on Price -->
                    <div class="form-group">
                        <label>Add-on Price</label>
                        <input type="number" id="editAddonPrice" name="addon_price" required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelEditModal">Cancel</button>
                    <button type="submit" class="btn-save">Save Changes</button>
                </div>

            </form>
        </div>
    </div>
    <!-- ========================
    ADD GALLON MODAL
======================== -->
    <div class="modal-overlay" id="addFlavorModal">
        <div class="modal-card">

            <form id="addGallonForm" action="{{ route('admin.gallons.store') }}" method="POST"
                enctype="multipart/form-data">
                @csrf

                <div class="modal-header">
                    <h3>Add New Gallon</h3>
                    <button type="button" class="modal-close" id="closeAddModal">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="modal-body">

                    <!-- Upload -->
                    <div class="upload-box">
                        <div class="upload-preview" id="imagePreview">
                            <span class="material-symbols-outlined">add_photo_alternate</span>
                        </div>

                        <input type="file" id="flavorImage" name="image" hidden>

                        <div class="upload-actions">
                            <label class="upload-label">Upload Photo</label>
                            <button type="button" class="btn-upload"
                                onclick="document.getElementById('flavorImage').click()">
                                Upload
                            </button>
                        </div>
                    </div>

                    <!-- Gallon Size + Quantity -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Gallon Size</label>
                            <input type="text" name="size" placeholder="Enter Size" autocomplete="off"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="quantity" placeholder="Enter Quantity" min="0"
                                required>
                        </div>
                    </div>

                    <!-- Add-on Price -->
                    <div class="form-group">
                        <label>Add-on Price</label>
                        <input type="number" name="addon_price" placeholder="Enter Price" min="0"
                            step="0.01" required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelAddModal">Cancel</button>
                    <button type="submit" class="btn-save">Add New Product</button>
                </div>

            </form>
        </div>
    </div>


    <!-- ========================
    DELETE CONFIRM MODAL
    ======================== -->
    <div class="delete-overlay" id="deleteConfirmModal">
        <form class="delete-modal" method="POST" id="deleteForm">
            @csrf
            @method('DELETE')

            <h3 class="delete-title">
                Are you sure you want to remove
                <span id="deleteFlavorName">this flavor</span>?
            </h3>

            <p class="delete-text">
                If you remove this product you will not have a chance to undo it.
            </p>

            <div class="delete-actions">
                <button type="button" class="btn-delete-cancel" id="cancelDelete">
                    No, Cancel
                </button>

                <button type="submit" class="btn-delete-confirm">
                    Yes, I'm sure
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            function setButtonLoadingState(button, isLoading, loadingText) {
                if (!button) return;

                if (isLoading) {
                    if (button.classList.contains("is-loading")) return;
                    button.dataset.originalHtml = button.innerHTML;
                    button.classList.add("is-loading");
                    button.disabled = true;
                    button.setAttribute("aria-busy", "true");
                    const text = loadingText || button.dataset.loadingText || "Processing...";
                    button.innerHTML =
                        '<span class="btn-loading-wrap"><span class="btn-spinner" aria-hidden="true"></span><span>' +
                        text + '</span></span>';
                    return;
                }

                button.classList.remove("is-loading");
                button.disabled = false;
                button.removeAttribute("aria-busy");
                if (button.dataset.originalHtml) {
                    button.innerHTML = button.dataset.originalHtml;
                    delete button.dataset.originalHtml;
                }
            }

            const pendingAlertMessage = sessionStorage.getItem("gallonPendingAlertMessage");
            const pendingAlertType = sessionStorage.getItem("gallonPendingAlertType");
            if (
                pendingAlertMessage &&
                typeof window.showGlobalAlert === "function" &&
                !document.getElementById("globalAlert")
            ) {
                window.showGlobalAlert(pendingAlertMessage, pendingAlertType || "success");
            }
            sessionStorage.removeItem("gallonPendingAlertMessage");
            sessionStorage.removeItem("gallonPendingAlertType");

            function openModal(modal) {
                modal.classList.add("show");
            }

            /* =====================
               UTILITIES
            ===================== */
            function closeModalSmooth(modal) {
                const card = modal.querySelector(".modal-card");
                card.style.transform = "translateX(120%)";
                card.style.opacity = "0";

                setTimeout(() => {
                    modal.classList.remove("show");
                    card.style.transform = "";
                    card.style.opacity = "";
                }, 450);
            }

            function resetAddModal() {
                document.querySelectorAll("#addFlavorModal input[type='text']").forEach(i => i.value = "");
                document.getElementById("imagePreview").innerHTML =
                    `<span class="material-symbols-outlined">add_photo_alternate</span>`;
                document.getElementById("flavorImage").value = "";
            }

            /* =====================
               FILTER & SEARCH
            ===================== */
            const filterBtn = document.getElementById("filterBtn");
            const filterDropdown = document.getElementById("filterDropdown");
            const filterCheckboxes = filterDropdown.querySelectorAll("input[type='checkbox']");
            const searchInput = document.getElementById("searchInput");
            let allRows = Array.from(document.querySelectorAll(".flavor-table tbody tr"));
            const selectAllGallons = document.getElementById("selectAllGallons");
            const bulkDeleteBtn = document.querySelector(".bulk-delete-btn");

            const pageNumbers = document.getElementById("pageNumbers");
            const pageInfo = document.getElementById("paginationInfo");
            const prevBtn = document.getElementById("prevBtn");
            const nextBtn = document.getElementById("nextBtn");

            const rowsPerPage = 10;
            let currentPage = 1;
            let filteredRows = [...allRows];

            function getRowCheckboxes() {
                return Array.from(document.querySelectorAll(".row-select"));
            }

            function getSelectedGallonIds() {
                return getRowCheckboxes().filter(cb => cb.checked).map(cb => cb.value);
            }

            function syncSelectAllState() {
                if (!selectAllGallons) return;
                const checkboxes = getRowCheckboxes();
                if (checkboxes.length === 0) {
                    selectAllGallons.checked = false;
                    selectAllGallons.indeterminate = false;
                    return;
                }
                const checkedCount = checkboxes.filter(cb => cb.checked).length;
                selectAllGallons.checked = checkedCount === checkboxes.length;
                selectAllGallons.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            }

            filterBtn.onclick = e => {
                e.stopPropagation();
                filterDropdown.classList.toggle("show");
            };

            document.addEventListener("click", () => filterDropdown.classList.remove("show"));
            filterDropdown.onclick = e => e.stopPropagation();

            filterCheckboxes.forEach(cb => {
                cb.addEventListener("change", () => {

                    // single status
                    if (["available", "out"].includes(cb.dataset.filter)) {
                        filterCheckboxes.forEach(o => {
                            if (o !== cb && ["available", "out"].includes(o.dataset
                                    .filter)) {
                                o.checked = false;
                            }
                        });
                    }

                    // single type
                    if (["ingredients", "flavor"].includes(cb.dataset.filter)) {
                        filterCheckboxes.forEach(o => {
                            if (o !== cb && ["ingredients", "flavor"].includes(o.dataset
                                    .filter)) {
                                o.checked = false;
                            }
                        });
                    }

                    applyFilters();
                });
            });

            function applyFilters() {
                const keyword = searchInput.value.toLowerCase();

                const selectedStatus = [...filterCheckboxes].find(cb =>
                    cb.checked && ["available", "out"].includes(cb.dataset.filter)
                );

                const selectedType = [...filterCheckboxes].find(cb =>
                    cb.checked && ["ingredients", "flavor"].includes(cb.dataset.filter)
                );

                filteredRows = allRows.filter(row => {
                    const text = row.innerText.toLowerCase();

                    if (keyword && !text.includes(keyword)) return false;
                    if (selectedStatus && !text.includes(
                            selectedStatus.dataset.filter === "out" ? "out of stock" : "available"
                        )) return false;
                    if (selectedType && !text.includes(selectedType.dataset.filter)) return false;

                    return true;
                });

                currentPage = 1;
                showPage(currentPage);
            }

            function showPage(page) {
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                allRows.forEach(r => r.style.display = "none");
                filteredRows.slice(start, end).forEach(r => r.style.display = "");

                const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

                if (totalPages <= 1) {
                    prevBtn.style.display = nextBtn.style.display = pageNumbers.style.display = "none";
                    pageInfo.style.display = "block";
                    pageInfo.textContent = filteredRows.length ?
                        `Showing ${filteredRows.length} data` :
                        "No results found";
                    syncSelectAllState();
                    return;
                }

                prevBtn.disabled = page === 1;
                nextBtn.disabled = page === totalPages;
                pageInfo.style.display = "none";
                syncSelectAllState();

                pageNumbers.innerHTML = "";
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement("button");
                    btn.className = "page-num" + (i === currentPage ? " active" : "");
                    btn.textContent = i;
                    btn.onclick = () => {
                        currentPage = i;
                        showPage(i);
                    };
                    pageNumbers.appendChild(btn);
                }
            }

            prevBtn.onclick = () => currentPage > 1 && showPage(--currentPage);
            nextBtn.onclick = () => currentPage < Math.ceil(filteredRows.length / rowsPerPage) && showPage(++
                currentPage);

            /* =====================
               SMOOTH CLIENT-SIDE SORT (no page refresh)
            ===================== */
            const gallonTbody = document.getElementById("gallonTableBody");
            document.querySelectorAll(".sort-link").forEach(link => {
                link.addEventListener("click", function(e) {
                    e.preventDefault();
                    const sortBy = this.dataset.sortBy;
                    const order = this.dataset.order;
                    if (!sortBy || !gallonTbody) return;

                    const rows = Array.from(gallonTbody.querySelectorAll("tr"));
                    const isAsc = order === "asc";

                    rows.sort((a, b) => {
                        if (sortBy === "quantity") {
                            const qa = parseInt(a.dataset.quantity, 10) || 0;
                            const qb = parseInt(b.dataset.quantity, 10) || 0;
                            return isAsc ? qa - qb : qb - qa;
                        }
                        if (sortBy === "price") {
                            const pa = parseFloat(a.dataset.price) || 0;
                            const pb = parseFloat(b.dataset.price) || 0;
                            return isAsc ? pa - pb : pb - pa;
                        }
                        if (sortBy === "status") {
                            const sa = a.dataset.status || "";
                            const sb = b.dataset.status || "";
                            const cmp = sa.localeCompare(sb);
                            return isAsc ? cmp : -cmp;
                        }
                        return 0;
                    });

                    gallonTbody.classList.add("is-sorting");
                    setTimeout(() => {
                        rows.forEach(row => gallonTbody.appendChild(row));
                        allRows = Array.from(gallonTbody.querySelectorAll("tr"));
                        applyFilters();
                        gallonTbody.classList.remove("is-sorting");
                    }, 120);
                    this.dataset.order = isAsc ? "desc" : "asc";
                });
            });

            searchInput.addEventListener("input", applyFilters);

            if (selectAllGallons) {
                selectAllGallons.addEventListener("change", () => {
                    const shouldCheck = selectAllGallons.checked;
                    getRowCheckboxes().forEach(cb => {
                        cb.checked = shouldCheck;
                    });
                    syncSelectAllState();
                });
            }

            document.addEventListener("change", (e) => {
                if (e.target.classList && e.target.classList.contains("row-select")) {
                    syncSelectAllState();
                }
            });

            showPage(currentPage);
            /* =====================
               ADD MODAL (FIXED & SAFE)
            ===================== */

            const addModal = document.getElementById("addFlavorModal");
            if (!addModal) return; // safety guard

            const addForm = document.getElementById("addGallonForm");
            const addBtn = document.querySelector(".btn-add");
            addForm?.addEventListener("submit", (e) => {
                const submitBtn = addForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Adding...");
                sessionStorage.setItem("gallonPendingAlertMessage", "Gallon added successfully");
                sessionStorage.setItem("gallonPendingAlertType", "success");
            });

            // open modal
            if (addBtn) {
                addBtn.onclick = () => {
                    resetAddModal();
                    addModal.classList.add("show");
                };
            }

            // close buttons
            document.getElementById("closeAddModal")?.addEventListener("click", () =>
                closeModalSmooth(addModal)
            );

            document.getElementById("cancelAddModal")?.addEventListener("click", () => {
                resetAddModal();
                closeModalSmooth(addModal);
            });

            // click outside modal
            addModal.addEventListener("click", e => {
                if (e.target === addModal) closeModalSmooth(addModal);
            });

            // reset modal
            function resetAddModal() {
                if (addForm) addForm.reset();

                document.getElementById("imagePreview").innerHTML =
                    `<span class="material-symbols-outlined">add_photo_alternate</span>`;

                document.getElementById("flavorImage").value = "";
            }

            // image preview
            document.getElementById("flavorImage")?.addEventListener("change", function() {
                const file = this.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = () => {
                    document.getElementById("imagePreview").innerHTML =
                        `<img src="${reader.result}">`;
                };
                reader.readAsDataURL(file);
            });

            /* =====================
               EDIT MODAL (FIXED)
            ===================== */

            const editModal = document.getElementById("editFlavorModal");
            const editForm = document.getElementById("editGallonForm");
            editForm?.addEventListener("submit", (e) => {
                const submitBtn = editForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Saving...");
                sessionStorage.setItem("gallonPendingAlertMessage", "Gallon updated successfully");
                sessionStorage.setItem("gallonPendingAlertType", "success");
            });

            // open edit modal
            document.querySelectorAll(".btn-edit").forEach(btn => {
                btn.addEventListener("click", () => {

                    const id = btn.dataset.id;
                    const size = btn.dataset.size;
                    const qty = btn.dataset.qty;
                    const price = btn.dataset.price;
                    const image = btn.dataset.image;

                    document.getElementById("editGallonSize").value = size;
                    document.getElementById("editQuantity").value = qty;
                    document.getElementById("editAddonPrice").value = price;

                    // reset file input
                    document.getElementById("editFlavorImage").value = "";

                    // preview image
                    document.getElementById("editImagePreview").innerHTML = image ?
                        `<img src="${image}">` :
                        `<span class="material-symbols-outlined">add_photo_alternate</span>`;

                    // set update URL
                    editForm.action = `/admin/gallons/${id}`;

                    openModal(editModal);
                });
            });

            // close buttons
            document.getElementById("closeEditModal")?.addEventListener("click", () =>
                closeModalSmooth(editModal)
            );

            document.getElementById("cancelEditModal")?.addEventListener("click", () =>
                closeModalSmooth(editModal)
            );

            // click outside modal
            editModal.addEventListener("click", e => {
                if (e.target === editModal) closeModalSmooth(editModal);
            });

            // image preview on change
            document.getElementById("editFlavorImage")?.addEventListener("change", function() {
                const file = this.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = () => {
                    document.getElementById("editImagePreview").innerHTML =
                        `<img src="${reader.result}">`;
                };
                reader.readAsDataURL(file);
            });


            /* =====================
               DELETE MODAL
            ===================== */
            const deleteModal = document.getElementById("deleteConfirmModal");
            const deleteName = document.getElementById("deleteFlavorName");
            const deleteForm = document.getElementById("deleteForm");
            const cancelDelete = document.getElementById("cancelDelete");
            deleteForm?.addEventListener("submit", (e) => {
                const submitBtn = deleteForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Deleting...");
                sessionStorage.setItem("gallonPendingAlertMessage", "Gallon deleted successfully");
                sessionStorage.setItem("gallonPendingAlertType", "success");
            });

            document.querySelectorAll(".btn-delete").forEach(btn => {
                btn.addEventListener("click", () => {
                    const row = btn.closest("tr");
                    const id = btn.dataset.id;

                    // set name
                    deleteName.textContent = row.querySelector(".flavor-col strong").textContent;

                    // set form action (Laravel route)
                    deleteForm.action = `/admin/gallons/${id}`;
                    deleteForm.querySelectorAll("input[name='gallon_ids[]']").forEach(i => i.remove());

                    deleteModal.classList.add("show");
                });
            });

            if (bulkDeleteBtn) {
                bulkDeleteBtn.addEventListener("click", () => {
                    const selectedIds = getSelectedGallonIds();
                    if (selectedIds.length === 0) {
                        if (typeof window.showGlobalAlert === "function") {
                            window.showGlobalAlert("Please select at least one gallon to delete.", "error");
                        }
                        return;
                    }

                    deleteName.textContent = selectedIds.length + " selected gallon(s)";
                    deleteForm.action = "{{ route('admin.gallons.bulk-destroy') }}";
                    deleteForm.querySelectorAll("input[name='gallon_ids[]']").forEach(i => i.remove());

                    selectedIds.forEach((id) => {
                        const input = document.createElement("input");
                        input.type = "hidden";
                        input.name = "gallon_ids[]";
                        input.value = id;
                        deleteForm.appendChild(input);
                    });

                    deleteModal.classList.add("show");
                });
            }

            cancelDelete.onclick = () => {
                setButtonLoadingState(deleteForm.querySelector("button[type='submit']"), false);
                deleteModal.classList.remove("show");
            };

            deleteModal.onclick = e => {
                if (e.target === deleteModal) {
                    setButtonLoadingState(deleteForm.querySelector("button[type='submit']"), false);
                    deleteModal.classList.remove("show");
                }
            };


            const clearSearchBtn = document.getElementById("clearSearch");

            searchInput.addEventListener("input", () => {
                clearSearchBtn.style.display = searchInput.value ? "block" : "none";
            });

            clearSearchBtn.addEventListener("click", () => {
                searchInput.value = "";
                clearSearchBtn.style.display = "none";
                applyFilters();
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const alert = document.getElementById("globalAlert");
            if (!alert) return;

            setTimeout(() => {
                alert.style.opacity = "0";
                alert.style.transform = "translateY(-10px)";
                setTimeout(() => alert.remove(), 300);
            }, 3000);
        });
    </script>

</body>

</html>
