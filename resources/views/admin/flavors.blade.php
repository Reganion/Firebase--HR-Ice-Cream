@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Flavor Management')

    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/Admin/flavor.css') }}">
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
            <div class="flavor-header">
                <h2>Flavor list</h2>

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

                            <div class="filter-section">
                                <label>
                                    <input type="checkbox" data-filter="plain">
                                    Plain Flavor
                                </label>
                                <label>
                                    <input type="checkbox" data-filter="special">
                                    Special Flavor
                                </label>
                                <label>
                                    <input type="checkbox" data-filter="1t">
                                    1 Toppings
                                </label>
                                <label>
                                    <input type="checkbox" data-filter="2t">
                                    2 Toppings
                                </label>
                            </div>
                        </div>
                    </div>


                    <button class="btn-add">
                        <span class="material-symbols-outlined">add</span>
                        Add Flavors
                    </button>
                </div>
            </div>

            <!-- TABLE -->
            <div class="table-card">

                <!-- HEADER -->
                <div class="table-header">
                    <div class="col check">
                        <input type="checkbox" id="selectAllFlavors" aria-label="Select all flavors">
                        <button type="button" class="bulk-delete-btn" id="bulkDeleteBtn" aria-label="Delete selected flavors">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                    <div class="col">
                         Flavors
                    </div>
                    @php
                        $sortBy = $sortBy ?? 'created_at';
                        $order = $order ?? 'desc';
                        $priceOrder = ($sortBy === 'price' && $order === 'asc') ? 'desc' : 'asc';
                        $statusOrder = ($sortBy === 'status' && $order === 'desc') ? 'asc' : 'desc';
                    @endphp
                    <div class="col col-sortable">
                        <a href="{{ route('admin.flavors', ['sort_by' => 'price', 'order' => $sortBy === 'price' ? $priceOrder : 'desc']) }}" class="sort-link" data-sort-by="price" data-order="{{ $sortBy === 'price' ? $priceOrder : 'desc' }}" aria-label="Sort by price">
                            
                            <span>Price</span>
                            <span class="sort-arrow" aria-hidden="true">
                                <svg class="sort-icon" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path d="M6 1L2 5.5h8L6 1z" fill="currentColor"/>
                                    <path d="M6 13l4-4.5H2l4 4.5z" fill="currentColor"/>
                                </svg>
                            </span>
                        </a>
                    </div>
                    <div class="col col-sortable">
                        <a href="{{ route('admin.flavors', ['sort_by' => 'status', 'order' => $sortBy === 'status' ? $statusOrder : 'asc']) }}" class="sort-link" data-sort-by="status" data-order="{{ $sortBy === 'status' ? $statusOrder : 'asc' }}" aria-label="Sort by status">
                           
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
                                <col style="width:70px">
                                <col style="width:40%">
                                <col style="width:20%">
                                <col style="width:17%">
                                <col style="width:30%">
                            </colgroup>
                            <tbody id="flavorTableBody">
                                @foreach ($flavors as $flavor)
                                    @php
                                        $ingredient = $flavorTypes->firstWhere('name', $flavor->flavor_type);
                                        $status =
                                            $ingredient && $ingredient->status === 'available' ? 'available' : 'out';
                                    @endphp

                                    <tr data-price="{{ (float) $flavor->price }}" data-status="{{ $status }}">
                                        <td>
                                            <input type="checkbox" class="row-select" value="{{ $flavor->id }}"
                                                data-flavor-name="{{ e($flavor->name) }}">
                                        </td>

                                        <td class="flavor-col">
                                            <img src="{{ asset($flavor->image ?? 'flavors/default.png') }}">
                                            <div>
                                                <strong>{{ $flavor->name }}</strong>
                                                <small>{{ $flavor->category }}</small>
                                            </div>
                                        </td>

                                        <td class="price">₱{{ number_format($flavor->price, 2) }}</td>

                                        <td>
                                            <span class="status {{ $status }}">
                                                <span class="dot"></span>
                                                {{ $status === 'available' ? 'Available' : 'Out of Stock' }}
                                            </span>
                                        </td>

                                        <td class="action">
                                            <button class="btn-edit" data-id="{{ $flavor->id }}"
                                                data-flavor-type="{{ $flavor->flavor_type }}"
                                                data-image="{{ asset($flavor->image ?? 'flavors/default.png') }}"
                                                data-mobile-image="{{ asset($flavor->mobile_image ?? $flavor->image ?? 'flavors/default.png') }}"
                                                title="Edit" aria-label="Edit">
                                                <span class="material-symbols-outlined">edit_square</span>
                                            </button>


                                            <button class="btn-delete" data-id="{{ $flavor->id }}" title="Delete"
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

    <div class="modal-overlay" id="editFlavorModal">

        <form class="modal-card edit-modal-card" method="POST" enctype="multipart/form-data" id="editFlavorForm">

            @csrf
            @method('PUT')

            <div class="modal-header">
                <h3>Edit Flavor</h3>
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

                <!-- Mobile display image -->
                <div class="upload-box">
                    <div class="upload-preview" id="editMobileImagePreview">
                        <span class="material-symbols-outlined">add_photo_alternate</span>
                    </div>
                    <input type="file" id="editMobileFlavorImage" name="mobile_image" hidden>
                    <div class="upload-actions">
                        <label class="upload-label">Display image (mobile)</label>
                        <button type="button" class="btn-upload"
                            onclick="document.getElementById('editMobileFlavorImage').click()">
                            Upload
                        </button>
                    </div>
                </div>

                <!-- Flavor Name + Flavor Type -->
                <div class="form-row">

                    <!-- Flavor Name -->
                    <div class="form-group">
                        <label>Flavor Name</label>
                        <input type="text" id="editFlavorName" name="name" required>
                    </div>

                    <div class="form-group">
                        <label>Flavor Type</label>

                        <div class="custom-select" id="editFlavorTypeSelect">
                            <div class="select-trigger">
                                <span class="selected">Select Flavor Type</span>
                                <span class="material-symbols-outlined">expand_more</span>
                            </div>

                            <div class="select-options">
                                @foreach ($flavorTypes as $flavor)
                                    <div class="option" data-value="{{ $flavor->name }}">
                                        {{ $flavor->name }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- value submitted to backend -->
                        <input type="hidden" name="flavor_type" id="editFlavorType" required>
                    </div>


                </div>


                <!-- Category + Price -->
                <div class="form-row">

                    <div class="form-group">
                        <label>Flavor Category</label>

                        <div class="custom-select" id="editCategorySelect">
                            <div class="select-trigger">
                                <span class="selected">Select Category</span>
                                <span class="material-symbols-outlined">expand_more</span>
                            </div>

                            <div class="select-options">
                                <div class="option">Plain Flavor</div>
                                <div class="option">Special Flavor</div>
                                <div class="option">1 Topping</div>
                                <div class="option">2 Toppings</div>
                            </div>
                        </div>

                        <input type="hidden" name="category" id="editCategoryValue" required>
                    </div>

                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" id="editPrice" name="price" required>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelEditModal">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>

        </form>
    </div>


    <!-- ========================
 ADD FLAVOR MODAL
======================== -->
    <div class="modal-overlay" id="addFlavorModal">

        <form class="modal-card" id="addFlavorForm" action="{{ route('admin.flavors.store') }}" method="POST"
            enctype="multipart/form-data">

            @csrf

            <div class="modal-header">
                <h3>Add New Flavor</h3>
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

                <!-- Mobile display image -->
                <div class="upload-box">
                    <div class="upload-preview" id="mobileImagePreview">
                        <span class="material-symbols-outlined">add_photo_alternate</span>
                    </div>
                    <input type="file" id="mobileFlavorImage" name="mobile_image" hidden>
                    <div class="upload-actions">
                        <label class="upload-label">Display image (mobile)</label>
                        <button type="button" class="btn-upload"
                            onclick="document.getElementById('mobileFlavorImage').click()">
                            Upload
                        </button>
                    </div>
                </div>

                <!-- Flavor Name + Flavor Type -->
                <div class="form-row">

                    <!-- Flavor Name -->
                    <div class="form-group">
                        <label>Flavor Name</label>
                        <input type="text" name="name" placeholder="Enter Flavor Name" required>
                    </div>

                    <div class="form-group">
                        <label>Flavor Type</label>

                        <div class="custom-select" id="addFlavorTypeSelect">
                            <div class="select-trigger">
                                <span class="selected">Select Flavor Type</span>
                                <span class="material-symbols-outlined">expand_more</span>
                            </div>

                            <div class="select-options">
                                @foreach ($flavorTypes as $flavor)
                                    <div class="option" data-value="{{ $flavor->name }}">
                                        {{ $flavor->name }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- submitted to backend -->
                        <input type="hidden" name="flavor_type" id="addFlavorType" required>
                    </div>


                </div>


                <!-- Category + Price -->
                <div class="form-row">

                    <!-- CATEGORY -->
                    <div class="form-group">
                        <label>Flavor Category</label>

                        <div class="custom-select" id="categorySelect">
                            <div class="select-trigger">
                                <span class="selected">Select Category</span>
                                <span class="material-symbols-outlined">expand_more</span>
                            </div>

                            <div class="select-options">
                                <div class="option">Plain Flavor</div>
                                <div class="option">Special Flavor</div>
                                <div class="option">1 Topping</div>
                                <div class="option">2 Toppings</div>
                            </div>
                        </div>

                        <!-- value set by JS -->
                        <input type="hidden" name="category" id="categoryValue" required>
                    </div>

                    <!-- PRICE -->
                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" name="price" placeholder="Enter Price" required>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelAddModal">
                    Cancel
                </button>
                <button type="submit" class="btn-save">
                    Add New Flavor
                </button>
            </div>

        </form>
    </div>


    <!-- ========================
 DELETE CONFIRM MODAL
======================== -->
    <div class="delete-overlay" id="deleteConfirmModal">

        <form class="delete-modal" method="POST" id="deleteFlavorForm">

            @csrf
            @method('DELETE')

            <h3 class="delete-title">
                Are you sure you want to remove
                <span id="deleteFlavorName">this flavor</span>?
            </h3>

            <p class="delete-text">
                If you remove this flavor you will not have a chance to undo it.
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

            const pendingAlertMessage = sessionStorage.getItem("flavorPendingAlertMessage");
            const pendingAlertType = sessionStorage.getItem("flavorPendingAlertType");
            if (
                pendingAlertMessage &&
                typeof window.showGlobalAlert === "function" &&
                !document.getElementById("globalAlert")
            ) {
                window.showGlobalAlert(pendingAlertMessage, pendingAlertType || "success");
            }
            sessionStorage.removeItem("flavorPendingAlertMessage");
            sessionStorage.removeItem("flavorPendingAlertType");

            initCustomSelect("editFlavorTypeSelect", "editFlavorType");
            initCustomSelect("addFlavorTypeSelect", "addFlavorType");

            function resetAddFlavorForm() {
                document.querySelector("#addFlavorTypeSelect .selected").textContent = "Select Flavor Type";
                document.querySelector("#addFlavorTypeSelect .selected").style.color = "#6b7280";
                document.getElementById("addFlavorType").value = "";
                // text & number inputs
                document.querySelectorAll(
                    "#addFlavorModal input[type='text'], #addFlavorModal input[type='number']").forEach(i => i
                    .value = "");

                // image preview
                document.getElementById("imagePreview").innerHTML =
                    `<span class="material-symbols-outlined">add_photo_alternate</span>`;
                document.getElementById("flavorImage").value = "";
                document.getElementById("mobileImagePreview").innerHTML =
                    `<span class="material-symbols-outlined">add_photo_alternate</span>`;
                document.getElementById("mobileFlavorImage").value = "";

                // custom select
                document.querySelector("#categorySelect .selected").textContent = "Select Category";
                document.querySelector("#categorySelect .selected").style.color = "#6b7280";
                document.getElementById("categoryValue").value = "";
            }

            function closeModalSmooth(modal) {
                const card = modal.querySelector(".modal-card");

                // trigger slide-out
                card.style.transform = "translateX(120%)";
                card.style.opacity = "0";

                // wait for animation before hiding overlay
                setTimeout(() => {
                    modal.classList.remove("show");

                    // reset so next open animates correctly
                    card.style.transform = "";
                    card.style.opacity = "";
                }, 450); // MUST match CSS transition time
            }

            /* =====================
               FILTER & SEARCH
            ===================== */
            const filterBtn = document.getElementById("filterBtn");
            const filterDropdown = document.getElementById("filterDropdown");
            const filterCheckboxes = filterDropdown.querySelectorAll("input[type='checkbox']");
            const searchInput = document.getElementById("searchInput");
            let allRows = Array.from(document.querySelectorAll(".flavor-table tbody tr"));
            const selectAllFlavors = document.getElementById("selectAllFlavors");
            const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

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

            function getSelectedFlavorIds() {
                return getRowCheckboxes()
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
            }

            function syncSelectAllState() {
                if (!selectAllFlavors) return;
                const checkboxes = getRowCheckboxes();
                if (checkboxes.length === 0) {
                    selectAllFlavors.checked = false;
                    selectAllFlavors.indeterminate = false;
                    return;
                }
                const checkedCount = checkboxes.filter(cb => cb.checked).length;
                selectAllFlavors.checked = checkedCount === checkboxes.length;
                selectAllFlavors.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            }

            filterBtn.onclick = e => {
                e.stopPropagation();
                filterDropdown.classList.toggle("show");
            };

            document.addEventListener("click", () => {
                filterDropdown.classList.remove("show");
            });

            filterDropdown.onclick = e => e.stopPropagation();

            filterCheckboxes.forEach(cb => {
                cb.addEventListener("change", () => {

                    if (["available", "out"].includes(cb.dataset.filter)) {
                        filterCheckboxes.forEach(o => {
                            if (o !== cb && ["available", "out"].includes(o.dataset
                                    .filter)) {
                                o.checked = false;
                            }
                        });
                    }

                    if (["plain", "special", "1t", "2t"].includes(cb.dataset.filter)) {
                        filterCheckboxes.forEach(o => {
                            if (o !== cb && ["plain", "special", "1t", "2t"].includes(o
                                    .dataset.filter)) {
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
                    cb.checked && ["plain", "special", "1t", "2t"].includes(cb.dataset.filter)
                );

                filteredRows = allRows.filter(row => {
                    const text = row.innerText.toLowerCase();

                    if (keyword && !text.includes(keyword)) return false;
                    if (selectedStatus && !text.includes(selectedStatus.dataset.filter === "out" ?
                            "out of stock" : "available")) return false;
                    if (selectedType && !text.includes(selectedType.dataset.filter.replace("t",
                            " topping"))) return false;

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

                // ALWAYS RESET VISIBILITY FIRST ✅
                prevBtn.style.display = "inline-flex";
                nextBtn.style.display = "inline-flex";
                pageNumbers.style.display = "flex";
                pageInfo.style.display = "none";

                if (totalPages <= 1) {
                    prevBtn.style.display = "none";
                    nextBtn.style.display = "none";
                    pageNumbers.style.display = "none";

                    pageInfo.style.display = "block";
                    pageInfo.textContent = filteredRows.length ?
                        `Showing ${filteredRows.length} data` :
                        "No results found";
                    syncSelectAllState();
                    return;
                }

                prevBtn.disabled = page === 1;
                nextBtn.disabled = page === totalPages;

                renderPagination(totalPages);
                syncSelectAllState();
            }


            function renderPagination(total) {
                pageNumbers.innerHTML = "";
                for (let i = 1; i <= total; i++) {
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
            const tbody = document.getElementById("flavorTableBody");
            document.querySelectorAll(".sort-link").forEach(link => {
                link.addEventListener("click", function(e) {
                    e.preventDefault();
                    const sortBy = this.dataset.sortBy;
                    const order = this.dataset.order;
                    if (!sortBy || !tbody) return;

                    const rows = Array.from(tbody.querySelectorAll("tr"));
                    const isAsc = order === "asc";

                    rows.sort((a, b) => {
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

                    tbody.classList.add("is-sorting");
                    setTimeout(() => {
                        rows.forEach(row => tbody.appendChild(row));
                        allRows = Array.from(tbody.querySelectorAll("tr"));
                        applyFilters();
                        tbody.classList.remove("is-sorting");
                    }, 120);
                    this.dataset.order = isAsc ? "desc" : "asc";
                });
            });

            searchInput.addEventListener("input", applyFilters);
            if (selectAllFlavors) {
                selectAllFlavors.addEventListener("change", () => {
                    const shouldCheck = selectAllFlavors.checked;
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
               MODAL
            ===================== */
            const modal = document.getElementById("addFlavorModal");
            const addFlavorForm = document.getElementById("addFlavorForm");
            const editFlavorForm = document.getElementById("editFlavorForm");
            const deleteFlavorForm = document.getElementById("deleteFlavorForm");

            addFlavorForm?.addEventListener("submit", (e) => {
                const submitBtn = addFlavorForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Adding...");
                sessionStorage.setItem("flavorPendingAlertMessage", "Flavor added successfully");
                sessionStorage.setItem("flavorPendingAlertType", "success");
            });

            editFlavorForm?.addEventListener("submit", (e) => {
                const submitBtn = editFlavorForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Saving...");
                sessionStorage.setItem("flavorPendingAlertMessage", "Flavor updated successfully");
                sessionStorage.setItem("flavorPendingAlertType", "success");
            });

            deleteFlavorForm?.addEventListener("submit", (e) => {
                const submitBtn = deleteFlavorForm.querySelector("button[type='submit']");
                if (submitBtn && submitBtn.classList.contains("is-loading")) {
                    e.preventDefault();
                    return;
                }
                setButtonLoadingState(submitBtn, true, "Deleting...");
                const selectedDeleteInputs = deleteFlavorForm.querySelectorAll("input[name='flavor_ids[]']");
                const deleteMessage = selectedDeleteInputs.length > 0 ?
                    `${selectedDeleteInputs.length} selected item(s) deleted successfully` :
                    "Flavor deleted successfully";
                sessionStorage.setItem("flavorPendingAlertMessage", deleteMessage);
                sessionStorage.setItem("flavorPendingAlertType", "success");
            });

            document.querySelector(".btn-add").onclick = () => {
                modal.classList.add("show");
            };

            document.getElementById("closeAddModal").onclick = () => {
                closeModalSmooth(modal);
            };

            document.getElementById("cancelAddModal").onclick = () => {
                resetAddFlavorForm();
                closeModalSmooth(modal);
            };

            modal.onclick = e => {
                if (e.target === modal) closeModalSmooth(modal);
            };


            /* =====================
               IMAGE PREVIEW
            ===================== */
            document.getElementById("flavorImage").addEventListener("change", function() {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => document.getElementById("imagePreview").innerHTML =
                    `<img src="${reader.result}">`;
                reader.readAsDataURL(file);
            });

            document.getElementById("mobileFlavorImage").addEventListener("change", function() {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => document.getElementById("mobileImagePreview").innerHTML =
                    `<img src="${reader.result}">`;
                reader.readAsDataURL(file);
            });

            (function() {
                const select = document.getElementById("editFlavorTypeSelect");
                if (!select) return;

                const trigger = select.querySelector(".select-trigger");
                const selected = select.querySelector(".selected");
                const options = select.querySelectorAll(".option");
                const hidden = document.getElementById("editFlavorType");

                trigger.onclick = e => {
                    e.stopPropagation();
                    select.classList.toggle("open");
                };

                options.forEach(opt => {
                    opt.onclick = () => {
                        selected.textContent = opt.textContent;
                        selected.style.color = "#111827";
                        hidden.value = opt.dataset.value;
                        select.classList.remove("open");
                    };
                });

                document.addEventListener("click", e => {
                    if (!select.contains(e.target)) select.classList.remove("open");
                });
            })();

            function initCustomSelect(selectId, hiddenInputId) {
                const select = document.getElementById(selectId);
                if (!select) return;

                const trigger = select.querySelector(".select-trigger");
                const selected = select.querySelector(".selected");
                const options = select.querySelectorAll(".option");
                const hidden = document.getElementById(hiddenInputId);

                trigger.onclick = () => {
                    select.classList.toggle("open");
                };

                options.forEach(opt => {
                    opt.onclick = () => {
                        selected.textContent = opt.textContent;
                        selected.style.color = "#111827";
                        hidden.value = opt.dataset.value;
                        select.classList.remove("open");
                    };
                });

                document.addEventListener("click", e => {
                    if (!select.contains(e.target)) {
                        select.classList.remove("open");
                    }
                });
            }


            /* =====================
               CUSTOM SELECT
            ===================== */
            const select = document.getElementById("categorySelect");
            const trigger = select.querySelector(".select-trigger");
            const selected = select.querySelector(".selected");
            const options = select.querySelectorAll(".option");
            const hiddenInput = document.getElementById("categoryValue");

            trigger.onclick = () => select.classList.toggle("open");

            options.forEach(opt => {
                opt.onclick = () => {
                    selected.textContent = opt.textContent;
                    selected.style.color = "#111827";
                    hiddenInput.value = opt.textContent;
                    select.classList.remove("open");
                };
            });

            document.addEventListener("click", e => {
                if (!select.contains(e.target)) select.classList.remove("open");
            });
            /* =====================
               EDIT MODAL
            ===================== */
            const editModal = document.getElementById("editFlavorModal");
            const closeEdit = document.getElementById("closeEditModal");
            const cancelEdit = document.getElementById("cancelEditModal");

            document.querySelectorAll(".btn-edit").forEach(btn => {
                btn.addEventListener("click", () => {

                    const row = btn.closest("tr");

                    const id = btn.dataset.id;
                    const name = row.querySelector(".flavor-col strong").textContent;
                    const category = row.querySelector(".flavor-col small").textContent;
                    const price = row.querySelector(".price").textContent.replace(/[₱,]/g, "");
                    const flavorType = btn.dataset.flavorType;
                    const image = btn.dataset.image;
                    const mobileImage = btn.dataset.mobileImage || image;

                    // form action
                    const form = document.getElementById("editFlavorForm");
                    form.action = `/admin/flavors/${id}`;

                    // inputs
                    document.getElementById("editFlavorName").value = name;
                    document.getElementById("editPrice").value = price;

                    // category
                    document.querySelector("#editCategorySelect .selected").textContent = category;
                    document.getElementById("editCategoryValue").value = category;

                    // flavor type
                    document.querySelector("#editFlavorTypeSelect .selected").textContent =
                        flavorType;
                    document.getElementById("editFlavorType").value = flavorType;

                    // Image previews
                    document.getElementById("editImagePreview").innerHTML =
                        `<img src="${image}" alt="Flavor Image">`;
                    document.getElementById("editMobileImagePreview").innerHTML =
                        `<img src="${mobileImage}" alt="Mobile Flavor Image">`;

                    // reset file inputs
                    document.getElementById("editFlavorImage").value = "";
                    document.getElementById("editMobileFlavorImage").value = "";

                    editModal.classList.add("show");
                });
            });



            closeEdit.onclick = cancelEdit.onclick = () => {
                closeModalSmooth(editModal);
            };

            editModal.onclick = e => {
                if (e.target === editModal) closeModalSmooth(editModal);
            };


            /* =====================
               EDIT IMAGE PREVIEW
            ===================== */
            document.getElementById("editFlavorImage").addEventListener("change", function() {
                const file = this.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = () => {
                    document.getElementById("editImagePreview").innerHTML =
                        `<img src="${reader.result}">`;
                };
                reader.readAsDataURL(file);
            });

            document.getElementById("editMobileFlavorImage").addEventListener("change", function() {
                const file = this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => {
                    document.getElementById("editMobileImagePreview").innerHTML =
                        `<img src="${reader.result}">`;
                };
                reader.readAsDataURL(file);
            });

            /* =====================
               EDIT CUSTOM SELECT
            ===================== */
            const editSelect = document.getElementById("editCategorySelect");
            const editTrigger = editSelect.querySelector(".select-trigger");
            const editSelected = editSelect.querySelector(".selected");
            const editOptions = editSelect.querySelectorAll(".option");
            const editHidden = document.getElementById("editCategoryValue");

            editTrigger.onclick = () => editSelect.classList.toggle("open");

            editOptions.forEach(opt => {
                opt.onclick = () => {
                    editSelected.textContent = opt.textContent;
                    editSelected.style.color = "#111827";
                    editHidden.value = opt.textContent;
                    editSelect.classList.remove("open");
                };
            });

            document.addEventListener("click", e => {
                if (!editSelect.contains(e.target)) editSelect.classList.remove("open");
            });

            /* =====================
               DELETE CONFIRM MODAL
            ===================== */
            const deleteModal = document.getElementById("deleteConfirmModal");
            const deleteName = document.getElementById("deleteFlavorName");
            const cancelDelete = document.getElementById("cancelDelete");
            const deleteForm = document.getElementById("deleteFlavorForm");

            document.querySelectorAll(".btn-delete").forEach(btn => {
                btn.addEventListener("click", () => {
                    const row = btn.closest("tr");
                    const id = btn.dataset.id;
                    const name = row.querySelector(".flavor-col strong").textContent;

                    deleteName.textContent = name;

                    // set form action
                    deleteForm.action = `/admin/flavors/${id}`;
                    deleteForm.querySelectorAll("input[name='flavor_ids[]']").forEach(i => i.remove());

                    deleteModal.classList.add("show");
                });
            });

            if (bulkDeleteBtn) {
                bulkDeleteBtn.addEventListener("click", () => {
                    const selectedIds = getSelectedFlavorIds();
                    if (selectedIds.length === 0) {
                        if (typeof window.showGlobalAlert === "function") {
                            window.showGlobalAlert("Please select at least one flavor to delete.", "error");
                        }
                        return;
                    }

                    deleteName.textContent = selectedIds.length + " selected flavor(s)";
                    deleteForm.action = "{{ route('admin.flavors.bulk-destroy') }}";
                    deleteForm.querySelectorAll("input[name='flavor_ids[]']").forEach(i => i.remove());
                    selectedIds.forEach(id => {
                        const input = document.createElement("input");
                        input.type = "hidden";
                        input.name = "flavor_ids[]";
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
