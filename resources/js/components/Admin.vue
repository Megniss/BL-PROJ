<template>
  <div class="page-layout">
  <AppNavbar admin @admin-password="pwdModal = true" />

  <!-- admin parole vēl nav ievadīta vai jānomaina -->
  <div v-if="gate !== 'ready'" class="admin-page container-xl py-5">
    <div class="admin-card admin-gate mx-auto">
      <div v-if="gate === 'checking'" class="text-center py-4">
        <span class="spinner-border spinner-border-sm text-success"></span>
      </div>
      <template v-else-if="gate === 'locked'">
        <h1 class="modal-title mb-2">{{ t('adminAuth.panel') }}</h1>
        <p class="modal-desc mb-3">{{ t('adminAuth.enterText') }}</p>
        <AdminUnlock @unlocked="onUnlocked" />
      </template>
      <template v-else-if="gate === 'error'">
        <div class="alert alert-danger mb-3">{{ t('admin.loadError') }}</div>
        <button class="btn btn-primary" @click="checkStatus">{{ t('adminAuth.retry') }}</button>
      </template>
      <template v-else-if="gate === 'must_change'">
        <h1 class="modal-title mb-2">{{ t('adminAuth.mustChangeTitle') }}</h1>
        <p class="modal-desc mb-3">{{ t('adminAuth.mustChangeText') }}</p>
        <AdminPasswordForm must-change @changed="onUnlocked({ must_change: false })" />
      </template>
    </div>
  </div>

  <div v-else class="admin-page container-xl py-4">
    <h1 class="admin-title mb-4">{{ t('admin.title') }}</h1>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <!-- cilnes -->
    <div class="admin-tabs-wrap mb-4">
      <div class="admin-tabs">
        <button v-for="tab in tabs" :key="tab.key"
          class="admin-tab-btn"
          :class="{ active: activeTab === tab.key }"
          @click="setTab(tab.key)">
          {{ t(tab.label) }}
          <span v-if="tab.key === 'support' && supportUnread > 0" class="admin-tab-badge">{{ supportUnread }}</span>
        </button>
      </div>
      <!-- rindu skaits tikai sarakstu tabiem (valodām, kartei un atbalstam nav vajadzīgs) -->
      <div v-if="listTabs.includes(activeTab)" class="admin-tabs-controls">
        <button class="admin-open-all-btn" :class="{ active: perPage === 99999 }" data-testid="open-all" @click="openAll">{{ perPage === 99999 ? t('admin.collapseAll') : t('admin.openAll') }}</button>
        <label class="admin-rows-pick">
          <span>{{ t('admin.rows') }}</span>
          <select class="form-select form-select-sm" data-testid="per-page" :value="perPage" @change="setPerPage(Number($event.target.value))">
            <option v-for="n in pageSizes" :key="n" :value="n">{{ n }}</option>
            <option :value="99999">{{ t('admin.rowsAll') }}</option>
          </select>
        </label>
      </div>
    </div>

    <!-- paziņojums par jauniem atbalsta pieteikumiem -->
    <div v-if="supportUnread > 0 && activeTab !== 'support'" class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 admin-support-alert" role="alert">
      <span>🛟 {{ t('admin.newComplaints').replace('{n}', supportUnread) }}</span>
      <button class="btn btn-sm btn-primary" @click="setTab('support')">{{ t('admin.openSupport') }}</button>
    </div>

    <Support v-if="activeTab === 'support'" ref="support" admin @read="fetchSupportUnread" />

    <!-- lietotāji kartē -->
    <div v-if="activeTab === 'map' && !loading[activeTab]" class="admin-card">
      <AdminUsersMap :users="users" />
    </div>

    <div v-if="loading[activeTab]" class="admin-card text-center py-5">
      <span class="spinner-border spinner-border-sm text-success"></span>
    </div>

    <!-- lietotāji -->
    <div v-show="activeTab === 'users' && !loading[activeTab]" class="admin-card">
      <div class="mt-1">
        <div class="filter-bar mb-3" :class="{ 'filters-open': showFilters }">
          <input v-model="filters.users.search" type="text" class="form-control form-control-sm m-keep" :placeholder="t('admin.filter.search')" />
          <button class="btn btn-sm btn-outline-secondary m-filter-btn" @click="showFilters = !showFilters">{{ t('admin.filters') }}<span v-if="hasFilters('users')"> •</span></button>
          <select v-model="filters.users.role" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allRoles') }}</option>
            <option value="admin">{{ t('admin.role.admin') }}</option>
            <option value="user">{{ t('admin.role.user') }}</option>
          </select>
          <select v-model="filters.users.status" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStatuses') }}</option>
            <option value="active">{{ t('admin.status.active') }}</option>
            <option value="blocked">{{ t('admin.status.blocked') }}</option>
          </select>
          <select v-model="filters.users.sort" class="form-select form-select-sm" :aria-label="t('admin.sort.label')">
            <option v-for="o in sortOptions.users" :key="o[0]" :value="o[0]">{{ t(o[1]) }}</option>
          </select>
          <button v-if="hasFilters('users')" class="btn btn-sm btn-outline-secondary m-keep" @click="resetFilters('users')">{{ t('books.clearFilters') }}</button>
        </div>
        <p v-if="!filteredUsers.length" class="text-muted text-center py-4 mb-0">{{ t('admin.user.none') }}</p>

        <!-- datorā kartītes -->
        <div class="swap-list desk-only">
          <article v-for="u in pagedUsers" :key="u.id" class="swap-item" :class="'user-item-' + (u.is_blocked ? 'blocked' : u.is_admin ? 'admin' : 'active')" data-testid="admin-user">
            <header class="swap-head">
              <span class="swap-badge" :class="u.is_admin ? 'user-badge-admin' : 'swap-badge-declined'">{{ u.is_admin ? t('admin.role.admin') : t('admin.role.user') }}</span>
              <span class="swap-badge" :class="u.is_blocked ? 'rating-badge-bad' : 'swap-badge-accepted'">{{ u.is_blocked ? t('admin.status.blocked') : t('admin.status.active') }}</span>
              <span class="swap-id">#{{ u.id }}</span>
              <span class="swap-when">{{ t('admin.col.joined') }} {{ u.joined }}</span>
              <div class="swap-actions">
                <template v-if="u.id !== currentUserId">
                  <button v-if="!u.is_blocked && !u.is_admin" class="ab btn btn-sm btn-outline-danger" @click="blockUser(u)">{{ t('admin.btn.block') }}</button>
                  <button v-if="u.is_blocked" class="ab btn btn-sm btn-outline-success" @click="unblockUser(u)">{{ t('admin.btn.unblock') }}</button>
                  <button v-if="!u.is_admin" class="ab btn btn-sm btn-outline-primary" @click="makeAdmin(u)">{{ t('admin.btn.makeAdmin') }}</button>
                  <button v-if="u.is_admin" class="ab btn btn-sm btn-outline-warning" @click="removeAdmin(u)">{{ t('admin.btn.removeAdmin') }}</button>
                </template>
                <span v-else class="text-muted small">{{ t('admin.status.you') }}</span>
              </div>
            </header>
            <div class="book-sides">
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.user.account') }}</p>
                <p class="swap-user">👤 {{ u.name }}</p>
                <p class="swap-author">✉️ {{ u.email }}</p>
              </div>
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.user.activity') }}</p>
                <ul class="book-facts">
                  <li>📚 {{ t('admin.col.books') }}: {{ u.books_count }}</li>
                  <li>
                    📍
                    <button v-if="u.latitude !== null" class="btn btn-link btn-sm p-0 align-baseline" @click="mapUser = u">{{ t('admin.showMap') }}</button>
                    <span v-else class="text-muted">{{ t('admin.user.noLocation') }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </article>
        </div>

        <!-- telefonā saraksts ar "skatīt vairāk" -->
        <div class="table-responsive mob-only">
        <table class="table table-hover align-middle m-cards" :aria-label="t('admin.users')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.name') }}</th>
              <th scope="col">{{ t('admin.col.email') }}</th>
              <th scope="col" class="text-center">{{ t('admin.col.books') }}</th>
              <th scope="col">{{ t('admin.col.joined') }}</th>
              <th scope="col">{{ t('admin.col.role') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col">{{ t('location.title') }}</th>
              <th scope="col" class="ab-col"></th>
              <th scope="col" class="ab-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr :class="{ open: isOpen('users', u.id) }" v-for="u in pagedUsers" :key="u.id">
              <td class="m-main fw-semibold">{{ u.name }}</td>
              <td :data-label="t('admin.col.email')" class="text-muted">{{ u.email }}</td>
              <td :data-label="t('admin.col.books')" class="text-center">{{ u.books_count }}</td>
              <td :data-label="t('admin.col.joined')" class="text-nowrap">{{ u.joined }}</td>
              <td class="m-main">
                <span class="stag" :class="u.is_admin ? 'stag-purple' : 'stag-gray'" :title="u.is_admin ? t('admin.role.admin') : t('admin.role.user')">
                  {{ u.is_admin ? 'A' : 'U' }}
                </span>
              </td>
              <td class="m-main">
                <span class="stag" :class="u.is_blocked ? 'stag-red' : 'stag-green'" :title="u.is_blocked ? t('admin.status.blocked') : t('admin.status.active')">
                  {{ u.is_blocked ? '✕' : '✓' }}
                </span>
              </td>
              <td :data-label="t('location.title')">
                <button v-if="u.latitude !== null" class="btn btn-sm btn-outline-secondary text-nowrap" @click="mapUser = u">📍 {{ t('admin.showMap') }}</button>
                <span v-else class="text-muted">-</span>
              </td>
              <template v-if="u.id !== currentUserId">
                <td class="ab-col">
                  <button v-if="!u.is_blocked && !u.is_admin" class="ab btn btn-sm btn-outline-danger" @click="blockUser(u)">{{ t('admin.btn.block') }}</button>
                  <button v-if="u.is_blocked" class="ab btn btn-sm btn-outline-success" @click="unblockUser(u)">{{ t('admin.btn.unblock') }}</button>
                </td>
                <td class="ab-col">
                  <button v-if="!u.is_admin" class="ab btn btn-sm btn-outline-primary" @click="makeAdmin(u)">{{ t('admin.btn.makeAdmin') }}</button>
                  <button v-if="u.is_admin" class="ab btn btn-sm btn-outline-warning" @click="removeAdmin(u)">{{ t('admin.btn.removeAdmin') }}</button>
                </td>
              </template>
              <template v-else>
                <td class="ab-col"></td>
                <td class="ab-col"><span class="text-muted small">{{ t('admin.status.you') }}</span></td>
              </template>
              <td class="m-toggle"><button class="btn btn-link btn-sm p-0" @click="toggleRow('users', u.id)">{{ isOpen('users', u.id) ? t('admin.less') : t('admin.more') }}</button></td>
            </tr>
          </tbody>
        </table>
        </div>
        <Pagination :current="pages.users" :total="totalPagesUsers" @change="pages.users = $event" />
      </div>
    </div>

    <!-- grāmatas -->
    <div v-show="activeTab === 'books' && !loading[activeTab]" class="admin-card">
      <div class="mt-1">
        <!-- kopsavilkums pa statusiem, uzspiežot atfiltrē -->
        <div class="swap-stats mb-3">
          <button type="button" class="swap-stat" :class="{ active: !filters.books.status }" data-testid="book-stat-all" @click="filters.books.status = ''">
            <strong>{{ books.length }}</strong> {{ t('admin.book.all') }}
          </button>
          <button v-for="st in ['Available', 'Pending', 'UnderReview']" :key="st" type="button"
            class="swap-stat" :class="['book-stat-' + st, { active: filters.books.status === st }]"
            @click="filters.books.status = filters.books.status === st ? '' : st">
            <strong>{{ bookCounts[st] ?? 0 }}</strong> {{ t('books.status.' + st) }}
          </button>
        </div>
        <div class="filter-bar mb-3" :class="{ 'filters-open': showFilters }">
          <input v-model="filters.books.search" type="text" class="form-control form-control-sm m-keep" :placeholder="t('admin.filter.searchBooks')" />
          <button class="btn btn-sm btn-outline-secondary m-filter-btn" @click="showFilters = !showFilters">{{ t('admin.filters') }}<span v-if="hasFilters('books')"> •</span></button>
          <select v-model="filters.books.status" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStatuses') }}</option>
            <option value="Available">{{ t('books.status.Available') }}</option>
            <option value="Pending">{{ t('books.status.Pending') }}</option>
            <option value="UnderReview">{{ t('books.status.UnderReview') }}</option>
          </select>
          <select v-model="filters.books.genre" class="form-select form-select-sm">
            <option value="">{{ t('search.allGenres') }}</option>
            <option v-for="g in bookOptions('genre')" :key="g" :value="g">{{ g }}</option>
          </select>
          <select v-model="filters.books.language" class="form-select form-select-sm">
            <option value="">{{ t('search.allLanguages') }}</option>
            <option v-for="l in bookOptions('language')" :key="l" :value="l">{{ l }}</option>
          </select>
          <select v-model="filters.books.condition" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allConditions') }}</option>
            <option v-for="c in ['New', 'Good', 'Fair', 'Worn']" :key="c" :value="c">{{ c }}</option>
          </select>
          <select v-model="filters.books.sort" class="form-select form-select-sm" :aria-label="t('admin.sort.label')">
            <option v-for="o in sortOptions.books" :key="o[0]" :value="o[0]">{{ t(o[1]) }}</option>
          </select>
          <button v-if="hasFilters('books')" class="btn btn-sm btn-outline-secondary m-keep" @click="resetFilters('books')">{{ t('books.clearFilters') }}</button>
        </div>
        <p v-if="!filteredBooks.length" class="text-muted text-center py-4 mb-0">{{ t('admin.book.none') }}</p>

        <!-- katra grāmata kā kartīte: kreisajā pusē grāmata, labajā īpašnieks un vēsture -->
        <div class="swap-list desk-only" :aria-label="t('admin.books')">
          <article v-for="b in pagedBooks" :key="b.id" class="swap-item" :class="'book-item-' + b.status" data-testid="admin-book">
            <header class="swap-head">
              <span class="swap-badge" :class="'book-badge-' + b.status">{{ t('books.status.' + b.status) || b.status }}</span>
              <span class="swap-id">#{{ b.id }}</span>
              <span class="swap-when">{{ t('admin.book.added') }} {{ fmtDate(b.created_at) }}</span>
              <div class="swap-actions">
                <button v-if="b.status !== 'UnderReview'" class="ab btn btn-sm btn-outline-info" @click="reviewBook(b)">{{ t('admin.btn.review') }}</button>
                <button v-if="b.status === 'UnderReview'" class="ab btn btn-sm btn-outline-success" @click="unreviewBook(b)">{{ t('admin.btn.unreview') }}</button>
                <button class="ab btn btn-sm btn-outline-danger" @click="deleteBook(b)">{{ t('admin.btn.delete') }}</button>
              </div>
            </header>

            <div class="book-sides">
              <div class="swap-side">
                <div class="swap-book">
                  <img v-if="b.cover_image" :src="'/storage/' + b.cover_image" :alt="b.title" class="swap-cover book-cover-lg" />
                  <div v-else class="swap-cover book-cover-lg swap-cover-ph" aria-hidden="true">📖</div>
                  <div class="swap-book-text">
                    <p class="swap-title" data-testid="admin-book-title">{{ b.title }}</p>
                    <p class="swap-author">{{ b.author }}</p>
                    <div class="book-chips">
                      <span class="book-chip">{{ b.genre }}</span>
                      <span class="book-chip">{{ b.language }}</span>
                      <span class="book-chip">{{ b.condition }}</span>
                    </div>
                    <p v-if="b.description" class="book-desc">{{ b.description }}</p>
                  </div>
                </div>
              </div>
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.col.owner') }}</p>
                <p class="swap-user">
                  👤 {{ b.owner?.name ?? '?' }}
                  <span v-if="b.owner?.blocked" class="swap-flag swap-flag-red">{{ t('admin.swap.blockedUser') }}</span>
                  <span v-if="b.owner?.deleted" class="swap-flag">{{ t('admin.swap.deletedUser') }}</span>
                </p>
                <ul class="book-facts">
                  <li>⇄ {{ t('admin.book.swaps').replace('{n}', b.swaps_accepted) }}<template v-if="b.swaps_pending"> · <span class="book-fact-pending">{{ t('admin.book.swapsPending').replace('{n}', b.swaps_pending) }}</span></template></li>
                  <li v-if="b.rating_count"><span class="stars-display">★</span> {{ b.rating_avg }} ({{ b.rating_count }})</li>
                  <li v-else class="text-muted">{{ t('admin.swap.noRatings') }}</li>
                </ul>
                <p v-if="b.status === 'UnderReview' && b.review_reason" class="book-review-reason">
                  <strong>{{ t('admin.book.reviewReason') }}:</strong> {{ b.review_reason }}
                </p>
              </div>
            </div>
          </article>
        </div>
        <!-- telefonā saraksts ar "skatīt vairāk" -->
        <div class="table-responsive mob-only">
        <table class="table table-hover align-middle m-cards" :aria-label="t('admin.books')">
          <tbody>
            <tr :class="{ open: isOpen('books', b.id) }" v-for="b in pagedBooks" :key="b.id">
              <td class="m-main fw-semibold">{{ b.title }}</td>
              <td class="m-main">
                <span class="stag" :class="{ 'stag-green': b.status === 'Available', 'stag-yellow': b.status === 'Pending', 'stag-blue': b.status === 'UnderReview' }" :title="t('books.status.' + b.status)">
                  {{ { Available: '✓', Pending: '~', UnderReview: '!' }[b.status] ?? '?' }}
                </span>
              </td>
              <td :data-label="t('admin.col.status')">{{ t('books.status.' + b.status) || b.status }}</td>
              <td :data-label="t('admin.col.author')">{{ b.author }}</td>
              <td :data-label="t('admin.col.genre')">{{ b.genre }} · {{ b.language }} · {{ b.condition }}</td>
              <td :data-label="t('admin.col.owner')">
                {{ b.owner?.name ?? '?' }}
                <span v-if="b.owner?.blocked" class="swap-flag swap-flag-red">{{ t('admin.swap.blockedUser') }}</span>
              </td>
              <td :data-label="t('admin.book.swapsCol')">{{ t('admin.book.swaps').replace('{n}', b.swaps_accepted) }}<template v-if="b.swaps_pending"> · {{ t('admin.book.swapsPending').replace('{n}', b.swaps_pending) }}</template></td>
              <td :data-label="t('admin.book.ratingCol')">
                <template v-if="b.rating_count"><span class="stars-display">★</span> {{ b.rating_avg }} ({{ b.rating_count }})</template>
                <span v-else class="text-muted">-</span>
              </td>
              <td :data-label="t('admin.col.added')">{{ fmtDate(b.created_at) }}</td>
              <td v-if="b.status === 'UnderReview' && b.review_reason" :data-label="t('admin.col.reason')">{{ b.review_reason }}</td>
              <td class="ab-col">
                <button v-if="b.status !== 'UnderReview'" class="ab btn btn-sm btn-outline-info" @click="reviewBook(b)">{{ t('admin.btn.review') }}</button>
                <button v-if="b.status === 'UnderReview'" class="ab btn btn-sm btn-outline-success" @click="unreviewBook(b)">{{ t('admin.btn.unreview') }}</button>
              </td>
              <td class="ab-col">
                <button class="ab btn btn-sm btn-outline-danger" @click="deleteBook(b)">{{ t('admin.btn.delete') }}</button>
              </td>
              <td class="m-toggle"><button class="btn btn-link btn-sm p-0" @click="toggleRow('books', b.id)">{{ isOpen('books', b.id) ? t('admin.less') : t('admin.more') }}</button></td>
            </tr>
          </tbody>
        </table>
        </div>
        <Pagination :current="pages.books" :total="totalPagesBooks" @change="pages.books = $event" />
      </div>
    </div>

    <!-- apmaiņas -->
    <div v-show="activeTab === 'swaps' && !loading[activeTab]" class="admin-card">
      <div class="mt-1">
        <!-- kopsavilkums, uzspiežot atfiltrē -->
        <div class="swap-stats mb-3">
          <button v-for="st in ['pending', 'accepted', 'declined']" :key="st" type="button"
            class="swap-stat" :class="['swap-stat-' + st, { active: filters.swaps.status === st }]"
            @click="filters.swaps.status = filters.swaps.status === st ? '' : st">
            <strong>{{ swapCounts[st] }}</strong> {{ t('admin.status.' + st) }}
          </button>
          <span v-if="swapCounts.stale" class="swap-stat swap-stat-stale">⚠ {{ t('admin.swap.stale').replace('{n}', swapCounts.stale) }}</span>
        </div>
        <div class="filter-bar mb-3" :class="{ 'filters-open': showFilters }">
          <input v-model="filters.swaps.search" type="text" class="form-control form-control-sm m-keep" :placeholder="t('admin.filter.searchSwaps')" />
          <button class="btn btn-sm btn-outline-secondary m-filter-btn" @click="showFilters = !showFilters">{{ t('admin.filters') }}<span v-if="hasFilters('swaps')"> •</span></button>
          <select v-model="filters.swaps.status" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStatuses') }}</option>
            <option value="pending">{{ t('admin.status.pending') }}</option>
            <option value="accepted">{{ t('admin.status.accepted') }}</option>
            <option value="declined">{{ t('admin.status.declined') }}</option>
          </select>
          <select v-model="filters.swaps.sort" class="form-select form-select-sm" :aria-label="t('admin.sort.label')">
            <option v-for="o in sortOptions.swaps" :key="o[0]" :value="o[0]">{{ t(o[1]) }}</option>
          </select>
          <button v-if="hasFilters('swaps')" class="btn btn-sm btn-outline-secondary m-keep" @click="resetFilters('swaps')">{{ t('books.clearFilters') }}</button>
        </div>
        <p v-if="!filteredSwaps.length" class="text-muted text-center py-4 mb-0">{{ t('admin.swap.none') }}</p>

        <!-- katra apmaiņa kā kartīte: kreisajā pusē pieprasītājs, labajā īpašnieks -->
        <div class="swap-list desk-only">
          <article v-for="s in pagedSwaps" :key="s.id" class="swap-item" :class="'swap-item-' + s.status" :data-testid="'admin-swap-' + s.id">
            <header class="swap-head">
              <span class="swap-badge" :class="'swap-badge-' + s.status">{{ t('admin.status.' + s.status) }}</span>
              <span class="swap-id">#{{ s.id }}</span>
              <span class="swap-when" :class="{ 'swap-when-stale': s.days_waiting >= 7 }">{{ swapWhen(s) }}</span>
              <div class="swap-actions">
                <button v-if="s.status === 'pending'" class="ab btn btn-sm btn-outline-success" @click="acceptSwap(s)">{{ t('admin.btn.accept') }}</button>
                <button v-if="s.status === 'pending'" class="ab btn btn-sm btn-outline-warning" @click="declineSwap(s)">{{ t('admin.btn.decline') }}</button>
                <button class="ab btn btn-sm btn-outline-danger" @click="deleteSwap(s)">{{ t('admin.btn.delete') }}</button>
              </div>
            </header>

            <div class="swap-sides">
              <div v-for="side in swapSides(s)" :key="side.key" class="swap-side">
                <p class="swap-role">{{ t(side.role) }}</p>
                <p class="swap-user">
                  👤 {{ side.user?.name ?? '?' }}
                  <span v-if="side.user?.blocked" class="swap-flag swap-flag-red">{{ t('admin.swap.blockedUser') }}</span>
                  <span v-if="side.user?.deleted" class="swap-flag">{{ t('admin.swap.deletedUser') }}</span>
                </p>
                <div v-if="side.book" class="swap-book">
                  <img v-if="side.book.cover_image" :src="'/storage/' + side.book.cover_image" :alt="side.book.title" class="swap-cover" />
                  <div v-else class="swap-cover swap-cover-ph" aria-hidden="true">📖</div>
                  <div class="swap-book-text">
                    <p class="swap-title">{{ side.book.title }}</p>
                    <p class="swap-author">{{ side.book.author }}</p>
                    <span v-if="['UnderReview', 'Removed'].includes(side.book.status)" class="swap-flag swap-flag-red">{{ t('books.status.' + side.book.status) || side.book.status }}</span>
                  </div>
                </div>
                <p v-else class="swap-book-gone">{{ t('admin.swap.bookDeleted') }}</p>
              </div>
              <div class="swap-arrow" aria-hidden="true">⇄</div>
            </div>

            <footer class="swap-foot">
              <span>{{ t('admin.swap.created') }}: {{ fmtDate(s.created_at) }}</span>
              <span v-if="s.status !== 'pending'">{{ swapClosedText(s) }}<template v-if="s.closed_at"> · {{ fmtDate(s.closed_at) }}</template></span>
              <span v-if="s.requester_dismissed || s.owner_dismissed" class="text-muted">
                {{ t('admin.swap.hidden') }}: {{ [s.requester_dismissed && s.requester?.name, s.owner_dismissed && s.owner?.name].filter(Boolean).join(', ') }}
              </span>
              <div v-if="s.status === 'accepted'" class="swap-ratings">
                <template v-if="s.ratings.length">
                  <span v-for="(r, i) in s.ratings" :key="i" class="swap-rating">
                    <span class="stars-display">{{ '★'.repeat(r.stars) }}{{ '☆'.repeat(5 - r.stars) }}</span>
                    {{ r.rater }}<template v-if="r.review">: "{{ r.review }}"</template>
                  </span>
                </template>
                <span v-else class="text-muted">{{ t('admin.swap.noRatings') }}</span>
              </div>
            </footer>
          </article>
        </div>
        <!-- telefonā saraksts ar "skatīt vairāk" -->
        <div class="table-responsive mob-only">
        <table class="table table-hover align-middle m-cards" :aria-label="t('admin.swaps')">
          <tbody>
            <tr :class="{ open: isOpen('swaps', s.id) }" v-for="s in pagedSwaps" :key="s.id">
              <td class="m-main fw-semibold">{{ s.requester?.name ?? '?' }} ⇄ {{ s.owner?.name ?? '?' }}</td>
              <td class="m-main">
                <span class="stag" :class="{ 'stag-yellow': s.status === 'pending', 'stag-green': s.status === 'accepted', 'stag-gray': s.status === 'declined' }" :title="t('admin.status.' + s.status)">
                  {{ { pending: '~', accepted: '✓', declined: '✕' }[s.status] ?? '?' }}
                </span>
              </td>
              <td :data-label="t('admin.col.status')">{{ t('admin.status.' + s.status) }}<template v-if="s.status === 'pending'"> · {{ swapWhen(s) }}</template></td>
              <td :data-label="t('admin.swap.requesterGives')">{{ s.offered ? s.offered.title + ' (' + s.offered.author + ')' : t('admin.swap.bookDeleted') }}</td>
              <td :data-label="t('admin.swap.ownerGives')">{{ s.wanted ? s.wanted.title + ' (' + s.wanted.author + ')' : t('admin.swap.bookDeleted') }}</td>
              <td :data-label="t('admin.swap.created')">{{ fmtDate(s.created_at) }}</td>
              <td v-if="s.status !== 'pending'" :data-label="t('admin.swap.closed')">{{ swapClosedText(s) }}<template v-if="s.closed_at"> · {{ fmtDate(s.closed_at) }}</template></td>
              <td v-if="s.ratings.length" :data-label="t('admin.book.ratingCol')">
                <span>
                  <span v-for="(r, i) in s.ratings" :key="i" class="d-block"><span class="stars-display">{{ '★'.repeat(r.stars) }}</span> {{ r.rater }}</span>
                </span>
              </td>
              <td class="ab-col">
                <button v-if="s.status === 'pending'" class="ab btn btn-sm btn-outline-success" @click="acceptSwap(s)">{{ t('admin.btn.accept') }}</button>
                <button v-if="s.status === 'pending'" class="ab btn btn-sm btn-outline-warning" @click="declineSwap(s)">{{ t('admin.btn.decline') }}</button>
              </td>
              <td class="ab-col">
                <button class="ab btn btn-sm btn-outline-danger" @click="deleteSwap(s)">{{ t('admin.btn.delete') }}</button>
              </td>
              <td class="m-toggle"><button class="btn btn-link btn-sm p-0" @click="toggleRow('swaps', s.id)">{{ isOpen('swaps', s.id) ? t('admin.less') : t('admin.more') }}</button></td>
            </tr>
          </tbody>
        </table>
        </div>
        <Pagination :current="pages.swaps" :total="totalPagesSwaps" @change="pages.swaps = $event" />
      </div>
    </div>

    <!-- vērtējumi -->
    <div v-show="activeTab === 'ratings' && !loading[activeTab]" class="admin-card">
      <div class="mt-1">
        <!-- kopsavilkums: vidējais un cik katru zvaigžņu skaitu, uzspiežot atfiltrē -->
        <div class="swap-stats mb-3">
          <span class="swap-stat rating-avg">{{ t('admin.rating.avg').replace('{avg}', ratingStats.avg).replace('{n}', ratings.length) }}</span>
          <button v-for="n in [5, 4, 3, 2, 1]" :key="n" type="button"
            class="swap-stat" :class="{ active: filters.ratings.stars === String(n) }"
            @click="filters.ratings.stars = filters.ratings.stars === String(n) ? '' : String(n)">
            <strong>{{ ratingStats.count[n] }}</strong> {{ n }}★
          </button>
          <span class="swap-stat rating-avg">{{ ratingStats.withReview }} {{ t('admin.rating.withReview') }}</span>
        </div>
        <div class="filter-bar mb-3" :class="{ 'filters-open': showFilters }">
          <input v-model="filters.ratings.search" type="text" class="form-control form-control-sm m-keep" :placeholder="t('admin.filter.searchBookRater')" />
          <button class="btn btn-sm btn-outline-secondary m-filter-btn" @click="showFilters = !showFilters">{{ t('admin.filters') }}<span v-if="hasFilters('ratings')"> •</span></button>
          <select v-model="filters.ratings.stars" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStars') }}</option>
            <option value="1">★</option>
            <option value="2">★★</option>
            <option value="3">★★★</option>
            <option value="4">★★★★</option>
            <option value="5">★★★★★</option>
          </select>
          <select v-model="filters.ratings.sort" class="form-select form-select-sm" :aria-label="t('admin.sort.label')">
            <option v-for="o in sortOptions.ratings" :key="o[0]" :value="o[0]">{{ t(o[1]) }}</option>
          </select>
          <button v-if="hasFilters('ratings')" class="btn btn-sm btn-outline-secondary m-keep" @click="resetFilters('ratings')">{{ t('books.clearFilters') }}</button>
        </div>
        <p v-if="!filteredRatings.length" class="text-muted text-center py-4 mb-0">{{ t('admin.rating.none') }}</p>

        <!-- katrs vērtējums kā kartīte: kurš vērtēja, kādu grāmatu un no kā to saņēma -->
        <div class="swap-list desk-only">
          <article v-for="r in pagedRatings" :key="r.id" class="swap-item rating-item" :class="'rating-item-' + ratingTone(r.stars)" :data-testid="'admin-rating-' + r.id">
            <header class="swap-head">
              <span class="rating-stars" :aria-label="r.stars + '/5'">{{ '★'.repeat(r.stars) }}<span class="rating-stars-off">{{ '☆'.repeat(5 - r.stars) }}</span></span>
              <span class="swap-badge" :class="'rating-badge-' + ratingTone(r.stars)">{{ r.stars }}/5</span>
              <span class="swap-id">#{{ r.id }}</span>
              <span class="swap-when">{{ fmtDate(r.date) }}</span>
              <div class="swap-actions">
                <button v-if="r.swap_id" class="ab btn btn-sm btn-outline-secondary" @click="openSwap(r.swap_id)">{{ t('admin.rating.swap').replace('{id}', r.swap_id) }}</button>
              </div>
            </header>

            <div class="swap-sides">
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.rating.rater') }}</p>
                <p class="swap-user">
                  👤 {{ r.rater?.name ?? '?' }}
                  <span v-if="r.rater?.blocked" class="swap-flag swap-flag-red">{{ t('admin.swap.blockedUser') }}</span>
                  <span v-if="r.rater?.deleted" class="swap-flag">{{ t('admin.swap.deletedUser') }}</span>
                </p>
                <blockquote v-if="r.review" class="rating-review">"{{ r.review }}"</blockquote>
                <p v-else class="rating-no-review">{{ t('admin.rating.noReview') }}</p>
              </div>
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.rating.book') }}</p>
                <div v-if="r.book" class="swap-book">
                  <img v-if="r.book.cover_image" :src="'/storage/' + r.book.cover_image" :alt="r.book.title" class="swap-cover" />
                  <div v-else class="swap-cover swap-cover-ph" aria-hidden="true">📖</div>
                  <div class="swap-book-text">
                    <p class="swap-title">{{ r.book.title }}</p>
                    <p class="swap-author">{{ r.book.author }}</p>
                    <p v-if="r.giver" class="swap-author">
                      {{ t('admin.rating.gaveBy') }} {{ r.giver.name }}
                      <span v-if="r.giver.blocked" class="swap-flag swap-flag-red">{{ t('admin.swap.blockedUser') }}</span>
                      <span v-if="r.giver.deleted" class="swap-flag">{{ t('admin.swap.deletedUser') }}</span>
                    </p>
                  </div>
                </div>
                <p v-else class="swap-book-gone">{{ t('admin.swap.bookDeleted') }}</p>
              </div>
              <div class="swap-arrow" aria-hidden="true">→</div>
            </div>
          </article>
        </div>
        <!-- telefonā saraksts ar "skatīt vairāk" -->
        <div class="table-responsive mob-only">
        <table class="table table-hover align-middle m-cards" :aria-label="t('admin.ratings')">
          <tbody>
            <tr :class="{ open: isOpen('ratings', r.id) }" v-for="r in pagedRatings" :key="r.id">
              <td class="m-main fw-semibold">{{ r.book?.title ?? t('admin.swap.bookDeleted') }}</td>
              <td class="m-main"><span class="stars-display">{{ '★'.repeat(r.stars) }}{{ '☆'.repeat(5 - r.stars) }}</span></td>
              <td :data-label="t('admin.col.author')">{{ r.book?.author ?? '-' }}</td>
              <td :data-label="t('admin.rating.rater')">{{ r.rater?.name ?? '?' }}</td>
              <td :data-label="t('admin.rating.gaveBy')">{{ r.giver?.name ?? '?' }}</td>
              <td :data-label="t('admin.col.review')">{{ r.review || t('admin.rating.noReview') }}</td>
              <td :data-label="t('admin.col.date')">{{ fmtDate(r.date) }}</td>
              <td class="ab-col">
                <button v-if="r.swap_id" class="ab btn btn-sm btn-outline-secondary" @click="openSwap(r.swap_id)">{{ t('admin.rating.swap').replace('{id}', r.swap_id) }}</button>
              </td>
              <td class="m-toggle"><button class="btn btn-link btn-sm p-0" @click="toggleRow('ratings', r.id)">{{ isOpen('ratings', r.id) ? t('admin.less') : t('admin.more') }}</button></td>
            </tr>
          </tbody>
        </table>
        </div>
        <Pagination :current="pages.ratings" :total="totalPagesRatings" @change="pages.ratings = $event" />
      </div>
    </div>

    <!-- valodas -->
    <div v-show="activeTab === 'langs' && !loading[activeTab]" class="admin-card">
      <div class="mt-1">

        <!-- valodu saraksts -->
        <div style="overflow-x:auto">
        <table class="table table-hover align-middle mb-3" style="min-width:560px">
          <thead>
            <tr>
              <th>{{ t('admin.col.flag') }}</th>
              <th>{{ t('admin.col.code') }}</th>
              <th>{{ t('admin.col.name') }}</th>
              <th>{{ t('admin.col.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="lang in allLanguages" :key="lang.code">
              <template v-if="editingLangRow === lang.code">
                <td>
                  <input v-model="langRowEdit.flag" class="form-control form-control-sm" style="width:70px" placeholder="ru" />
                  <span v-if="langRowEdit.flag" :class="'fi fi-' + langRowEdit.flag.toLowerCase()" style="font-size:1.2rem;border-radius:2px;margin-top:3px;display:block"></span>
                </td>
                <td><code>{{ lang.code }}</code></td>
                <td><input v-model="langRowEdit.name" class="form-control form-control-sm" style="width:130px" /></td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-primary" @click="saveLangRow(lang)">{{ t('admin.btn.save') }}</button>
                    <button class="btn btn-sm btn-outline-secondary" @click="editingLangRow = null">{{ t('modal.cancel') }}</button>
                  </div>
                </td>
              </template>
              <template v-else>
                <td>
                  <span v-if="lang.flag" :class="'fi fi-' + lang.flag" style="font-size:1.4rem;border-radius:2px"></span>
                  <span v-else style="font-size:0.85rem;font-weight:600">{{ lang.code.toUpperCase() }}</span>
                </td>
                <td><code>{{ lang.code }}</code></td>
                <td>{{ lang.name }}</td>
                <td style="white-space:nowrap">
                  <div class="d-flex gap-1 align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" @click="startEditLangRow(lang)">{{ t('admin.btn.edit') }}</button>
                    <button class="btn btn-sm btn-outline-primary" @click="openLangEditor(lang)">{{ t('admin.btn.editTranslations') }}</button>
                    <button v-if="lang.is_active && lang.code !== 'en'" class="btn btn-sm btn-outline-danger" @click="deactivateLang(lang)">{{ t('admin.btn.removeLang') }}</button>
                    <button v-if="!lang.is_active" class="btn btn-sm btn-outline-success" @click="reactivateLang(lang)">{{ t('admin.btn.restoreLang') }}</button>
                  </div>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
        </div>

        <!-- jaunas valodas forma -->
        <div v-if="!addingLang">
          <button class="btn btn-sm btn-outline-primary" @click="addingLang = true">{{ t('admin.btn.addLang') }}</button>
        </div>
        <div v-else class="d-flex gap-2 flex-wrap align-items-end">
          <div>
            <label class="form-label small mb-1">{{ t('admin.lang.flag') }}</label>
            <input v-model="newLang.flag" class="form-control form-control-sm" style="width:80px" placeholder="ru" />
            <div class="form-text" style="font-size:0.7rem">{{ t('admin.lang.flagHint') }}</div>
            <span v-if="newLang.flag" :class="'fi fi-' + newLang.flag.toLowerCase()" style="font-size:1.4rem;border-radius:2px;margin-top:4px;display:block"></span>
          </div>
          <div>
            <label class="form-label small mb-1">{{ t('admin.lang.code') }}</label>
            <input v-model="newLang.code" class="form-control form-control-sm" style="width:80px" placeholder="de" />
          </div>
          <div>
            <label class="form-label small mb-1">{{ t('admin.lang.name') }}</label>
            <input v-model="newLang.name" class="form-control form-control-sm" style="width:140px" placeholder="German" />
          </div>
          <button class="btn btn-sm btn-primary" @click="addLanguage">{{ t('admin.btn.save') }}</button>
          <button class="btn btn-sm btn-outline-secondary" @click="addingLang = false">{{ t('modal.cancel') }}</button>
        </div>

        <!-- tulkojumu redaktors -->
        <div v-if="editingLang" class="lang-editor-overlay" @click.self="editingLang = null">
          <div class="lang-editor-modal">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h3 class="mb-0">{{ t('admin.lang.editTitle') }}: <code>{{ editingLang }}</code></h3>
              <button class="btn btn-sm btn-outline-secondary" @click="editingLang = null">✕</button>
            </div>
            <p class="text-muted small mb-3">{{ t('admin.lang.editHint') }}</p>
            <div class="lang-editor-list">
              <div v-for="(defaultVal, key) in allTranslationKeys" :key="key" class="lang-editor-row">
                <div class="lang-editor-key">{{ key }}</div>
                <input
                  class="form-control form-control-sm lang-editor-input"
                  :placeholder="defaultVal"
                  :value="langTranslations[key] ?? ''"
                  @input="langTranslations[key] = $event.target.value"
                />
              </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <button class="btn btn-outline-secondary btn-sm" @click="editingLang = null">{{ t('modal.cancel') }}</button>
              <button class="btn btn-primary btn-sm" :disabled="langSaving" @click="saveLangTranslations">
                {{ langSaving ? '…' : t('admin.btn.save') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- žurnāls -->
    <div v-show="activeTab === 'logs' && !loading[activeTab]" class="admin-card">
      <div class="mt-1">
        <div class="filter-bar mb-3" :class="{ 'filters-open': showFilters }">
          <input v-model="filters.logs.search" type="text" class="form-control form-control-sm m-keep" :placeholder="t('admin.filter.search')" />
          <button class="btn btn-sm btn-outline-secondary m-filter-btn" @click="showFilters = !showFilters">{{ t('admin.filters') }}<span v-if="hasFilters('logs')"> •</span></button>
          <select v-model="filters.logs.action" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allActions') }}</option>
            <option value="block_user">{{ t('admin.log.block_user') }}</option>
            <option value="unblock_user">{{ t('admin.log.unblock_user') }}</option>
            <option value="make_admin">{{ t('admin.log.make_admin') }}</option>
            <option value="remove_admin">{{ t('admin.log.remove_admin') }}</option>
            <option value="delete_book">{{ t('admin.log.delete_book') }}</option>
            <option value="review_book">{{ t('admin.log.review_book') }}</option>
            <option value="unreview_book">{{ t('admin.log.unreview_book') }}</option>
            <option value="accept_swap">{{ t('admin.log.accept_swap') }}</option>
            <option value="decline_swap">{{ t('admin.log.decline_swap') }}</option>
            <option value="delete_swap">{{ t('admin.log.delete_swap') }}</option>
            <option value="support_reply">{{ t('admin.log.support_reply') }}</option>
            <option value="close_complaint">{{ t('admin.log.close_complaint') }}</option>
            <option value="add_language">{{ t('admin.log.add_language') }}</option>
            <option value="edit_language">{{ t('admin.log.edit_language') }}</option>
            <option value="remove_language">{{ t('admin.log.remove_language') }}</option>
            <option value="restore_language">{{ t('admin.log.restore_language') }}</option>
            <option value="edit_translations">{{ t('admin.log.edit_translations') }}</option>
            <option value="admin_unlock_failed">{{ t('admin.log.admin_unlock_failed') }}</option>
            <option value="change_admin_password">{{ t('admin.log.change_admin_password') }}</option>
            <option value="delete_account">{{ t('admin.log.delete_account') }}</option>
          </select>
          <select v-model="filters.logs.sort" class="form-select form-select-sm" :aria-label="t('admin.sort.label')">
            <option v-for="o in sortOptions.logs" :key="o[0]" :value="o[0]">{{ t(o[1]) }}</option>
          </select>
          <button v-if="hasFilters('logs')" class="btn btn-sm btn-outline-secondary m-keep" @click="resetFilters('logs')">{{ t('books.clearFilters') }}</button>
        </div>
        <p v-if="!filteredLogs.length" class="text-muted text-center py-4 mb-0 desk-only">{{ t('admin.logs.empty') }}</p>

        <!-- datorā kartītes -->
        <div class="swap-list desk-only">
          <article v-for="l in pagedLogs" :key="l.id" class="swap-item" :class="'log-item-' + logTone(l.action)" data-testid="admin-log">
            <header class="swap-head">
              <span class="log-action-badge">{{ t('admin.log.' + l.action) }}</span>
              <span class="swap-id">#{{ l.id }}</span>
              <span class="swap-when">{{ l.date }}</span>
            </header>
            <div class="book-sides">
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.col.admin') }}</p>
                <p class="swap-user mb-0">👤 {{ l.admin ?? '?' }}</p>
              </div>
              <div class="swap-side">
                <p class="swap-role">{{ t('admin.col.target') }}</p>
                <p class="swap-title">{{ l.target_name || '-' }}</p>
                <p v-if="l.reason" class="book-review-reason"><strong>{{ t('admin.col.reason') }}:</strong> {{ l.reason }}</p>
              </div>
            </div>
          </article>
        </div>

        <!-- telefonā saraksts ar "skatīt vairāk" -->
        <div class="table-responsive mob-only">
          <table class="table table-hover align-middle m-cards" :aria-label="t('admin.logs')">
            <thead>
              <tr>
                <th scope="col">{{ t('admin.col.date') }}</th>
                <th scope="col">{{ t('admin.col.admin') }}</th>
                <th scope="col">{{ t('admin.col.action') }}</th>
                <th scope="col">{{ t('admin.col.target') }}</th>
                <th scope="col">{{ t('admin.col.reason') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredLogs.length === 0">
                <td colspan="5" class="text-center text-muted py-3">{{ t('admin.logs.empty') }}</td>
              </tr>
              <tr :class="{ open: isOpen('logs', l.id) }" v-for="l in pagedLogs" :key="l.id">
                <td :data-label="t('admin.col.date')" class="text-nowrap">{{ l.date }}</td>
                <td :data-label="t('admin.col.admin')">{{ l.admin }}</td>
                <td class="m-main"><span class="log-action-badge">{{ t('admin.log.' + l.action) }}</span></td>
                <td class="m-main">{{ l.target_name }}</td>
                <td :data-label="t('admin.col.reason')">{{ l.reason || '-' }}</td>
                <td class="m-toggle"><button class="btn btn-link btn-sm p-0" @click="toggleRow('logs', l.id)">{{ isOpen('logs', l.id) ? t('admin.less') : t('admin.more') }}</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination :current="pages.logs" :total="totalPagesLogs" @change="pages.logs = $event" />
      </div>
    </div>

  </div>
  <AppFooter />

  <Teleport to="body">
    <!-- jauna admina pagaidu parole -->
    <div v-if="makeAdminUser" class="modal-overlay" @click.self="makeAdminUser = null">
      <form class="modal-card modal-card--sm" @submit.prevent="confirmMakeAdmin">
        <h2 class="modal-title mb-2">{{ t('admin.btn.makeAdmin') }}: {{ makeAdminUser.name }}</h2>
        <p class="modal-desc mb-3">{{ t('adminAuth.tempText') }}</p>
        <label for="temp-admin-pwd" class="form-label fw-semibold">{{ t('adminAuth.temp') }}</label>
        <input id="temp-admin-pwd" v-model="tempPassword" class="form-control" type="password" autocomplete="new-password" minlength="12" required />
        <small class="text-muted">{{ t('adminAuth.hint') }}</small>
        <div v-if="makeAdminError" class="alert alert-danger py-2 px-3 mt-3">{{ makeAdminError }}</div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary" @click="makeAdminUser = null">{{ t('settings.deleteCancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="tempPassword.length < 12">{{ t('admin.btn.makeAdmin') }}</button>
        </div>
      </form>
    </div>

    <!-- lietotāja aptuvenā atrašanās vieta -->
    <div v-if="mapUser" class="modal-overlay" @click.self="mapUser = null">
      <div class="modal-card">
        <h2 class="modal-title mb-3">{{ mapUser.name }}</h2>
        <LocationPicker readonly :latitude="mapUser.latitude" :longitude="mapUser.longitude" />
        <div class="d-flex justify-content-end mt-3">
          <button class="btn btn-outline-secondary" @click="mapUser = null">{{ t('adminAuth.back') }}</button>
        </div>
      </div>
    </div>

    <!-- savas admin paroles maiņa -->
    <div v-if="pwdModal" class="modal-overlay" @click.self="pwdModal = false">
      <div class="modal-card modal-card--sm">
        <h2 class="modal-title mb-3">{{ t('adminAuth.changeBtn') }}</h2>
        <AdminPasswordForm @changed="pwdModal = false; showToast(t('adminAuth.changed'))" @cancel="pwdModal = false" />
      </div>
    </div>
  </Teleport>
  </div>
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import { invalidateOverrides, setLocale } from '../langStore.js'
import { translations } from '../translations.js'
import { showToast } from '../toast.js'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'
import Pagination from './Pagination.vue'
import Support from './Support.vue'
import AdminUnlock from './AdminUnlock.vue'
import AdminPasswordForm from './AdminPasswordForm.vue'
import LocationPicker from './LocationPicker.vue'
import AdminUsersMap from './AdminUsersMap.vue'
import langMixin from '../langMixin.js'

const DEFAULT_FILTERS = {
  users: { search: '', role: '', status: '', sort: 'joined:asc' },
  books: { search: '', status: '', genre: '', language: '', condition: '', sort: 'created_at:desc' },
  swaps: { search: '', status: '', sort: 'created_at:desc' },
  ratings: { search: '', stars: '', sort: 'date:desc' },
  logs: { search: '', action: '', sort: 'date:desc' },
}

const SORT_OPTIONS = {
  users: [
    ['joined:asc', 'admin.sort.oldest'], ['joined:desc', 'admin.sort.newest'],
    ['name:asc', 'admin.sort.nameAsc'], ['name:desc', 'admin.sort.nameDesc'],
    ['books_count:desc', 'admin.sort.mostBooks'],
  ],
  books: [
    ['created_at:desc', 'admin.sort.newest'], ['created_at:asc', 'admin.sort.oldest'],
    ['title:asc', 'admin.sort.titleAsc'], ['title:desc', 'admin.sort.titleDesc'],
    ['author:asc', 'admin.sort.authorAsc'], ['owner_name:asc', 'admin.sort.ownerAsc'],
    ['genre:asc', 'admin.sort.genreAsc'], ['status:asc', 'admin.sort.status'],
  ],
  swaps: [
    ['created_at:desc', 'admin.sort.newest'], ['created_at:asc', 'admin.sort.oldest'],
    ['status:asc', 'admin.sort.status'], ['days_waiting:desc', 'admin.sort.waitingLongest'],
  ],
  ratings: [
    ['date:desc', 'admin.sort.newest'], ['date:asc', 'admin.sort.oldest'],
    ['stars:desc', 'admin.sort.starsDesc'], ['stars:asc', 'admin.sort.starsAsc'],
  ],
  logs: [
    ['date:desc', 'admin.sort.newest'], ['date:asc', 'admin.sort.oldest'],
  ],
}

// 'lauks:asc' vai 'lauks:desc'; skaitļi kā skaitļi, teksts pēc latviešu alfabēta
// cik rindu lapā pēc noklusējuma - apmaiņu kartītes ir lielas, tāpēc mazāk
function defaultPerPage(tab) {
  return { books: 6, swaps: 4, ratings: 6 }[tab] ?? 10
}

function sortList(list, value) {
  const [field, dir] = value.split(':')
  return [...list].sort((a, b) => {
    const x = a[field], y = b[field]
    const res = typeof x === 'number' && typeof y === 'number'
      ? x - y
      : String(x ?? '').localeCompare(String(y ?? ''), 'lv', { sensitivity: 'base' })
    return dir === 'asc' ? res : -res
  })
}

export default {
  name: 'AdminPanel',
  components: { AppNavbar, AppFooter, Pagination, Support, AdminUnlock, AdminPasswordForm, LocationPicker, AdminUsersMap },
  mixins: [langMixin],

  data() {
    return {
      gate: 'checking',
      pwdModal: false,
      makeAdminUser: null,
      mapUser: null,
      tempPassword: '',
      makeAdminError: '',
      users: [],
      books: [],
      swaps: [],
      ratings: [],
      logs: [],
      allLanguages: [],
      editingLang: null,
      langTranslations: {},
      langSaving: false,
      newLang: { code: '', name: '', flag: '' },
      addingLang: false,
      editingLangRow: null,
      langRowEdit: { flag: '', name: '' },
      error: '',
      loaded: {},
      loading: {},
      activeTab: this.$route?.params?.tab || 'users',
      supportUnread: 0,
      supportTimer: null,
      tabs: [
        { key: 'users', label: 'admin.users' },
        { key: 'books', label: 'admin.books' },
        { key: 'swaps', label: 'admin.swaps' },
        { key: 'ratings', label: 'admin.ratings' },
        { key: 'langs', label: 'admin.languages' },
        { key: 'map', label: 'adminMap.tab' },
        { key: 'support', label: 'admin.support' },
        { key: 'logs', label: 'admin.logs' },
      ],
      filters: structuredClone(DEFAULT_FILTERS),
      sortOptions: SORT_OPTIONS,
      showFilters: false,
      openRows: {},
      pages: (() => {
        const tab = window.location.pathname.split('/admin/')[1] || 'users'
        const page = Number(new URLSearchParams(window.location.search).get('page')) || 1
        return { users: 1, books: 1, swaps: 1, ratings: 1, logs: 1, [tab]: page }
      })(),
      listTabs: ['users', 'books', 'swaps', 'ratings', 'logs'],
      perPage: this.$route?.query?.per_page === 'all' ? 99999
        : (Number(this.$route?.query?.per_page) || defaultPerPage(this.$route?.params?.tab || 'users')),

    }
  },

  computed: {
    currentUserId() {
      return authStore.user?.id
    },

    filteredUsers() {
      let list = this.users
      const s = this.filters.users.search.toLowerCase()
      if (s) list = list.filter(u => u.name.toLowerCase().includes(s) || u.email.toLowerCase().includes(s))
      if (this.filters.users.role === 'admin') list = list.filter(u => u.is_admin)
      if (this.filters.users.role === 'user') list = list.filter(u => !u.is_admin)
      if (this.filters.users.status === 'active') list = list.filter(u => !u.is_blocked)
      if (this.filters.users.status === 'blocked') list = list.filter(u => u.is_blocked)
      return sortList(list, this.filters.users.sort)
    },

    filteredBooks() {
      let list = this.books
      const s = this.filters.books.search.toLowerCase()
      if (/^#\d+$/.test(s)) list = list.filter(b => '#' + b.id === s)
      else if (s) list = list.filter(b =>
        [b.title, b.author, b.owner_name, b.genre, '#' + b.id].some(v => v && v.toLowerCase().includes(s))
      )
      const f = this.filters.books
      for (const key of ['status', 'genre', 'language', 'condition']) {
        if (f[key]) list = list.filter(b => b[key] === f[key])
      }
      return sortList(list, f.sort)
    },

    // grāmatu skaits pa statusiem
    bookCounts() {
      const out = {}
      for (const b of this.books) out[b.status] = (out[b.status] ?? 0) + 1
      return out
    },

    // skaitļi kopsavilkumam virs saraksta
    swapCounts() {
      const out = { pending: 0, accepted: 0, declined: 0, stale: 0 }
      for (const s of this.swaps) {
        out[s.status] = (out[s.status] ?? 0) + 1
        if (s.days_waiting >= 7) out.stale++
      }
      return out
    },

    filteredSwaps() {
      let list = this.swaps
      const s = this.filters.swaps.search.toLowerCase()
      // "#12" - tieši šī apmaiņa (piem. no vērtējumu taba)
      if (/^#\d+$/.test(s)) list = list.filter(sw => '#' + sw.id === s)
      // meklē pēc abiem lietotājiem, abām grāmatām, autoriem un #id
      else if (s) list = list.filter(sw =>
        [sw.requester?.name, sw.owner?.name, sw.offered?.title, sw.offered?.author, sw.wanted?.title, sw.wanted?.author, '#' + sw.id]
          .some(v => v && v.toLowerCase().includes(s))
      )
      if (this.filters.swaps.status) list = list.filter(sw => sw.status === this.filters.swaps.status)
      return sortList(list, this.filters.swaps.sort)
    },

    // vidējais, cik katrā zvaigžņu skaitā un cik ar komentāru
    ratingStats() {
      const count = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 }
      let sum = 0
      let withReview = 0
      for (const r of this.ratings) {
        count[r.stars]++
        sum += r.stars
        if (r.review) withReview++
      }
      const avg = this.ratings.length ? (sum / this.ratings.length).toFixed(1) : '-'
      return { count, avg, withReview }
    },

    filteredRatings() {
      let list = this.ratings
      const s = this.filters.ratings.search.toLowerCase()
      // meklē grāmatā, autorā, abos lietotājos un komentārā
      if (s) list = list.filter(r =>
        [r.book?.title, r.book?.author, r.rater?.name, r.giver?.name, r.review, '#' + r.id]
          .some(v => v && v.toLowerCase().includes(s))
      )
      if (this.filters.ratings.stars) list = list.filter(r => r.stars === Number(this.filters.ratings.stars))
      return sortList(list, this.filters.ratings.sort)
    },

    allTranslationKeys() {
      // EN ir galvenais atslēgu saraksts
      return translations['en'] ?? {}
    },

    filteredLogs() {
      let list = this.logs
      const s = this.filters.logs.search.toLowerCase()
      if (s) list = list.filter(l => l.admin?.toLowerCase().includes(s) || l.target_name?.toLowerCase().includes(s))
      if (this.filters.logs.action) list = list.filter(l => l.action === this.filters.logs.action)
      return sortList(list, this.filters.logs.sort)
    },

    // izvēles saraksts + taba noklusējums, ja tā tur nav (piem. 4 apmaiņām)
    pageSizes() {
      const list = [4, 6, 10, 15, 25, 50]
      if (this.perPage !== 99999 && !list.includes(this.perPage)) list.push(this.perPage)
      return list.sort((a, b) => a - b)
    },

    pagedUsers() { return this.paginate(this.filteredUsers, this.pages.users) },
    pagedBooks() { return this.paginate(this.filteredBooks, this.pages.books) },
    pagedSwaps() { return this.paginate(this.filteredSwaps, this.pages.swaps) },
    pagedRatings() { return this.paginate(this.filteredRatings, this.pages.ratings) },
    pagedLogs() { return this.paginate(this.filteredLogs, this.pages.logs) },

    totalPagesUsers() { return Math.ceil(this.filteredUsers.length / this.perPage) || 1 },
    totalPagesBooks() { return Math.ceil(this.filteredBooks.length / this.perPage) || 1 },
    totalPagesSwaps() { return Math.ceil(this.filteredSwaps.length / this.perPage) || 1 },
    totalPagesRatings() { return Math.ceil(this.filteredRatings.length / this.perPage) || 1 },
    totalPagesLogs() { return Math.ceil(this.filteredLogs.length / this.perPage) || 1 },
  },

  watch: {
    perPage() { this.syncUrl() },
    pages: { deep: true, handler() { this.syncUrl() } },
    'filters.users': { deep: true, handler() { this.pages.users = 1 } },
    'filters.books': { deep: true, handler() { this.pages.books = 1 } },
    'filters.swaps': { deep: true, handler() { this.pages.swaps = 1 } },
    'filters.ratings': { deep: true, handler() { this.pages.ratings = 1 } },
    'filters.logs': { deep: true, handler() { this.pages.logs = 1 } },
  },

  async mounted() {
    // stāvoklis no URL, ja atvērts pa tiešo saiti
    if (this.$route.params.tab) this.activeTab = this.$route.params.tab
    if (this.$route.query.page) this.pages[this.activeTab] = Number(this.$route.query.page)
    if (this.$route.query.per_page) {
      this.perPage = this.$route.query.per_page === 'all' ? 99999 : Number(this.$route.query.per_page)
    }

    await this.checkStatus()
  },

  beforeUnmount() {
    clearInterval(this.supportTimer)
  },

  methods: {
    showToast,

    // cik atbalsta pieteikumos ir jaunas lietotāju ziņas
    async fetchSupportUnread() {
      try {
        const { data } = await axios.get('/api/admin/complaints/unread-count')
        // ja atnāk jauns kamēr atvērts support, atjauno sarakstu
        if (data.count > this.supportUnread && this.activeTab === 'support') {
          this.$refs.support?.loadList()
        }
        this.supportUnread = data.count
      } catch { /* klusums */ }
    },

    // telefonā rindā redzams tikai galvenais, pārējais pēc "Skatīt vairāk"
    isOpen(tab, id) {
      return !!this.openRows[tab + id]
    },

    toggleRow(tab, id) {
      this.openRows[tab + id] = !this.openRows[tab + id]
    },

    hasFilters(tab) {
      return JSON.stringify(this.filters[tab]) !== JSON.stringify(DEFAULT_FILTERS[tab])
    },

    resetFilters(tab) {
      this.filters[tab] = structuredClone(DEFAULT_FILTERS[tab])
    },

    // izvēles no tā, kas grāmatās tiešām ir
    bookOptions(key) {
      return [...new Set(this.books.map(b => b[key]).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'lv'))
    },

    async checkStatus() {
      this.gate = 'checking'
      try {
        const { data } = await axios.get('/api/admin/status')
        await this.onUnlocked(data)
      } catch (err) {
        // uz profilu sūta tikai tad, ja tiešām nav admins
        if (err.response?.status === 403) this.$router.push({ name: 'dashboard' })
        else this.gate = 'error'
      }
    },

    async onUnlocked(status) {
      if (status.unlocked === false) {
        this.gate = 'locked'
        return
      }
      if (status.must_change) {
        this.gate = 'must_change'
        return
      }
      this.gate = 'ready'
      this.loaded = {}

      // pārbauda jaunus pieteikumus uzreiz un tad reizi 30 sekundēs
      this.fetchSupportUnread()
      clearInterval(this.supportTimer)
      this.supportTimer = setInterval(this.fetchSupportUnread, 30000)

      await this.loadTab(this.activeTab)
    },

    paginate(list, page, size = this.perPage) {
      const start = (page - 1) * size
      return list.slice(start, start + size)
    },

    syncUrl() {
      const tab = this.activeTab
      this.$router.replace({
        name: 'admin',
        params: { tab },
        query: {
          page: this.pages[tab] ?? 1,
          per_page: this.perPage === 99999 ? 'all' : this.perPage,
        },
      })
    },

    setTab(key) {
      this.activeTab = key
      this.perPage = defaultPerPage(key)
      this.syncUrl()
      this.loadTab(key)
    },

    openAll() {
      this.setPerPage(this.perPage === 99999 ? defaultPerPage(this.activeTab) : 99999)
    },

    // mainot rindu skaitu, sāk no pirmās lapas
    setPerPage(n) {
      this.perPage = n
      this.pages = { users: 1, books: 1, swaps: 1, ratings: 1, logs: 1 }
    },

    async loadTab(key) {
      if (this.loaded[key]) return
      this.loading[key] = true
      try {
        const endpoints = {
          users: '/api/admin/users',
          map: '/api/admin/users',
          books: '/api/admin/books',
          swaps: '/api/admin/swaps',
          ratings: '/api/admin/ratings',
          logs: '/api/admin/logs',
          langs: '/api/admin/languages',
        }
        const targets = {
          users: 'users',
          map: 'users',
          books: 'books',
          swaps: 'swaps',
          ratings: 'ratings',
          logs: 'logs',
          langs: 'allLanguages',
        }
        if (!endpoints[key]) return
        const { data } = await axios.get(endpoints[key])
        this[targets[key]] = data
        this.loaded[key] = true
      } catch (err) {
        // 8h beigušās - atkal prasa paroli
        if (err.response?.data?.admin_locked) this.gate = 'locked'
        else this.error = this.t('admin.loadError')
      } finally {
        this.loading[key] = false
      }
    },

    async blockUser(u) {
      if (!confirm(this.t('admin.confirm.block').replace('{name}', u.name))) return
      await axios.patch(`/api/admin/users/${u.id}/block`)
      u.is_blocked = true
    },

    async unblockUser(u) {
      await axios.patch(`/api/admin/users/${u.id}/unblock`)
      u.is_blocked = false
    },

    makeAdmin(u) {
      this.makeAdminUser = u
      this.tempPassword = ''
      this.makeAdminError = ''
    },

    async confirmMakeAdmin() {
      const u = this.makeAdminUser
      try {
        await axios.patch(`/api/admin/users/${u.id}/make-admin`, { admin_password: this.tempPassword })
        u.is_admin = true
        u.is_blocked = false
        this.makeAdminUser = null
      } catch {
        this.makeAdminError = this.t('dash.genericError')
      }
    },

    async removeAdmin(u) {
      if (!confirm(this.t('admin.confirm.removeAdmin').replace('{name}', u.name))) return
      await axios.patch(`/api/admin/users/${u.id}/remove-admin`)
      u.is_admin = false
    },

    async reviewBook(b) {
      const reason = prompt(this.t('admin.prompt.reviewReason').replace('{title}', b.title))
      if (reason === null) return
      if (!reason.trim()) {
        alert(this.t('admin.prompt.reviewReasonRequired'))
        return
      }
      try {
        await axios.patch(`/api/admin/books/${b.id}/review`, { reason: reason.trim() })
        b.status = 'UnderReview'
        b.review_reason = reason.trim()
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    async unreviewBook(b) {
      try {
        await axios.patch(`/api/admin/books/${b.id}/unreview`)
        b.status = 'Available'
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    async deleteBook(b) {
      const reason = prompt(this.t('admin.prompt.deleteReason').replace('{title}', b.title))
      if (reason === null) return
      if (!reason.trim()) {
        alert(this.t('admin.prompt.deleteReasonRequired'))
        return
      }
      try {
        await axios.delete(`/api/admin/books/${b.id}`, { data: { reason: reason.trim() } })
        this.books = this.books.filter(x => x.id !== b.id)
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    // pieņemot mainās arī citi pieprasījumi uz tām pašām grāmatām, tāpēc ielādē sarakstu no jauna
    async acceptSwap(s) {
      if (!confirm(this.t('admin.confirm.acceptSwap').replace('{a}', s.offered?.title ?? '?').replace('{b}', s.wanted?.title ?? '?'))) return
      try {
        await axios.patch(`/api/admin/swaps/${s.id}/accept`)
      } catch (err) {
        showToast(err.response?.data?.message || this.t('dash.genericError'), 'error')
      }
      await this.reloadSwaps()
    },

    async declineSwap(s) {
      if (!confirm(this.t('admin.confirm.declineSwap').replace('{a}', s.offered?.title ?? '?').replace('{b}', s.wanted?.title ?? '?'))) return
      try {
        await axios.patch(`/api/admin/swaps/${s.id}/decline`)
      } catch (err) {
        showToast(err.response?.data?.message || this.t('dash.genericError'), 'error')
      }
      await this.reloadSwaps()
    },

    async reloadSwaps() {
      this.loaded.swaps = false
      await this.loadTab('swaps')
    },

    // žurnāla krāsa: dzēšana/bloķēšana sarkana, atjaunošana zaļa, pārējais pelēks
    logTone(action) {
      if (/^(delete_|block_user|remove_|decline_|admin_unlock_failed)/.test(action)) return 'bad'
      if (/^(unblock_user|unreview_book|restore_|accept_)/.test(action)) return 'good'
      return 'plain'
    },

    // krāsa pēc zvaigznēm: 4-5 labi, 3 vidēji, 1-2 slikti
    ratingTone(stars) {
      return stars >= 4 ? 'good' : stars === 3 ? 'mid' : 'bad'
    },

    // no vērtējuma uz apmaiņu žurnālu, atfiltrētu uz šo apmaiņu
    openSwap(id) {
      this.filters.swaps = { search: '#' + id, status: '', sort: 'created_at:desc' }
      this.setTab('swaps')
    },

    // kreisā puse pieprasītājs (atdod offered), labā īpašnieks (atdod wanted)
    swapSides(s) {
      return [
        { key: 'r', role: 'admin.swap.requesterGives', user: s.requester, book: s.offered },
        { key: 'o', role: 'admin.swap.ownerGives', user: s.owner, book: s.wanted },
      ]
    },

    swapWhen(s) {
      if (s.status !== 'pending') return ''
      return s.days_waiting > 0
        ? this.t('admin.swap.waiting').replace('{n}', s.days_waiting)
        : this.t('admin.swap.waitingToday')
    },

    // kurš pieņēma/noraidīja, veciem ierakstiem nav zināms
    swapClosedText(s) {
      if (!s.closed_by) return this.t('admin.swap.unknown')
      return this.t('admin.swap.' + (s.status === 'accepted' ? 'acc' : 'dec') + '.' + s.closed_by)
    },

    fmtDate(value) {
      if (!value) return ''
      const d = new Date(value)
      const pad = n => String(n).padStart(2, '0')
      return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    },

    async deleteSwap(s) {
      if (!confirm(this.t('admin.confirm.deleteSwap'))) return
      await axios.delete(`/api/admin/swaps/${s.id}`)
      this.swaps = this.swaps.filter(x => x.id !== s.id)
    },

    startEditLangRow(lang) {
      this.editingLangRow = lang.code
      this.langRowEdit = { flag: lang.flag, name: lang.name }
    },

    async saveLangRow(lang) {
      if (!this.langRowEdit.flag || !this.langRowEdit.name) return
      try {
        const { data } = await axios.patch(`/api/admin/languages/${lang.code}`, this.langRowEdit)
        lang.flag = data.flag
        lang.name = data.name
        // atjauno arī navbar valodu pārslēdzēju
        const entry = this.langStore.languages.find(l => l.code === lang.code)
        if (entry) { entry.flag = data.flag; entry.name = data.name }
        this.editingLangRow = null
      } catch (e) {
        alert(e.response?.data?.message || this.t('dash.genericError'))
      }
    },

    async addLanguage() {
      if (!this.newLang.code || !this.newLang.name || !this.newLang.flag) return
      try {
        const { data } = await axios.post('/api/admin/languages', this.newLang)
        this.allLanguages.push(data)
        this.langStore.languages.push({ code: data.code, name: data.name, flag: data.flag })
        this.newLang = { code: '', name: '', flag: '' }
        this.addingLang = false
      } catch (e) {
        alert(e.response?.data?.message || this.t('dash.genericError'))
      }
    },

    async deactivateLang(lang) {
      if (!confirm(this.t('admin.confirm.deactivateLang').replace('{name}', lang.name))) return
      await axios.patch(`/api/admin/languages/${lang.code}/deactivate`)
      lang.is_active = false
      // izņem arī no navbar
      const idx = this.langStore.languages.findIndex(l => l.code === lang.code)
      if (idx !== -1) this.langStore.languages.splice(idx, 1)
      // ja bija šī valoda, pārslēdz uz EN
      if (this.langStore.locale === lang.code) setLocale('en')
    },

    async reactivateLang(lang) {
      await axios.patch(`/api/admin/languages/${lang.code}/reactivate`)
      lang.is_active = true
      if (!this.langStore.languages.find(l => l.code === lang.code)) {
        this.langStore.languages.push({ code: lang.code, name: lang.name, flag: lang.flag })
      }
    },

    async openLangEditor(lang) {
      this.editingLang = lang.code
      // sāk ar failu, jaunām valodām pamatā EN
      const staticBase = { ...(translations[lang.code] ?? translations['en']) }
      const { data } = await axios.get(`/api/admin/translations/${lang.code}`)
      // db labojumi svarīgāki par failu
      this.langTranslations = { ...staticBase, ...data }
    },

    async saveLangTranslations() {
      this.langSaving = true
      try {
        await axios.post(`/api/admin/translations/${this.editingLang}`, { overrides: this.langTranslations })
        invalidateOverrides(this.editingLang)
        this.editingLang = null
      } finally {
        this.langSaving = false
      }
    },
  }
}
</script>

<style scoped>
.admin-page { max-width: 1200px; }

.admin-tabs-wrap {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  border-bottom: 2px solid var(--border);
}
.admin-tabs {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.admin-tabs-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-bottom: 4px;
}
.admin-open-all-btn {
  background: none;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 4px 12px;
  font-size: 0.8rem;
  color: var(--muted);
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
}
.admin-rows-pick { display: flex; align-items: center; gap: 6px; margin: 0; font-size: .8rem; color: var(--muted); white-space: nowrap; }
.admin-rows-pick .form-select { width: auto; }
.admin-open-all-btn:hover { color: #1a1612; border-color: #1a1612; }
.admin-open-all-btn.active { color: #3a6b3e; border-color: #3a6b3e; font-weight: 600; }
.admin-tab-btn {
  background: none;
  border: none;
  padding: 8px 18px;
  font-size: 0.9rem;
  font-weight: 500;
  color: #7a7068;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  border-radius: 4px 4px 0 0;
  transition: color 0.15s, border-color 0.15s;
}
.admin-tab-btn:hover { color: #1a1612; }
.admin-tab-btn.active { color: #3a6b3e; border-bottom-color: #3a6b3e; font-weight: 600; }
/* jauno atbalsta pieteikumu skaits */
.admin-tab-badge {
  display: inline-block;
  min-width: 18px;
  padding: 0 5px;
  margin-left: 4px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  line-height: 18px;
  text-align: center;
}

.filter-bar {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}
.filter-bar .form-control-sm,
.filter-bar .form-select-sm {
  max-width: 220px;
}

.admin-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--color-primary, #3a6b3e);
}

.admin-card {
  background: var(--card-bg, #fff);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
}

.admin-section-title {
  font-size: 1.1rem;
  font-weight: 600;
}

.table th { font-weight: 600; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }

/* pogu kolonnas */
.ab-col { width: 1%; white-space: nowrap; padding-left: .35rem; padding-right: .35rem; }
.ab { min-width: 72px; white-space: nowrap; font-size: .72rem; padding: .2rem .45rem; }

/* statusa birkas */
.stag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 22px;
  border-radius: 5px;
  font-size: .75rem;
  font-weight: 700;
  line-height: 1;
  cursor: default;
}
.stag-green  { background: #d1fae5; color: #166534; }
.stag-red    { background: #fee2e2; color: #991b1b; }
.stag-yellow { background: #fef9c3; color: #854d0e; }
.stag-gray   { background: #f1f5f9; color: #64748b; }
.stag-blue   { background: #dbeafe; color: #1e40af; }
.stag-purple { background: #ede9fe; color: #5b21b6; }

.stars-display { color: #f5a623; letter-spacing: .1em; }

/* apmaiņu tabs */
.swap-stats { display: flex; flex-wrap: wrap; gap: 8px; }
.swap-stat {
  border: 1px solid var(--border);
  background: transparent;
  border-radius: 999px;
  padding: 4px 14px;
  font-size: .85rem;
  color: inherit;
  cursor: pointer;
}
.swap-stat strong { margin-right: 2px; }
.swap-stat-pending strong { color: #a16207; }
.swap-stat-accepted strong { color: #166534; }
.swap-stat-declined strong { color: #64748b; }
.swap-stat.active { border-color: #3a6b3e; background: #3a6b3e; color: #fff; }
.swap-stat.active strong { color: #fff; }
.swap-stat-stale { cursor: default; border-color: #f59e0b; color: #a16207; background: #fffbeb; }

.swap-list { display: flex; flex-direction: column; gap: 12px; }
.swap-item {
  border: 1px solid var(--border);
  border-left: 4px solid #cbd5e1;
  border-radius: 10px;
  padding: 12px 14px;
}
.swap-item-pending { border-left-color: #eab308; }
.swap-item-accepted { border-left-color: #22c55e; }

.swap-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
.swap-badge { font-size: .75rem; font-weight: 700; padding: 2px 10px; border-radius: 999px; }
.swap-badge-pending { background: #fef9c3; color: #854d0e; }
.swap-badge-accepted { background: #d1fae5; color: #166534; }
.swap-badge-declined { background: #f1f5f9; color: #64748b; }
.swap-id { font-size: .8rem; color: var(--muted); }
.swap-when { font-size: .85rem; color: var(--muted); }
.swap-when-stale { color: #b45309; font-weight: 600; }
.swap-actions { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }

.swap-sides { display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: stretch; }
.swap-side:first-child { order: 1; }
.swap-arrow { order: 2; align-self: center; font-size: 1.4rem; color: var(--muted); }
.swap-side:nth-child(2) { order: 3; }
.swap-side {
  background: rgba(0, 0, 0, .025);
  border-radius: 8px;
  padding: 8px 10px;
  min-width: 0;
}
.swap-side p { margin: 0; }
.swap-role { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
.swap-user { font-weight: 600; margin: 2px 0 6px !important; }
.swap-book { display: flex; gap: 10px; align-items: flex-start; }
.swap-cover { width: 40px; height: 58px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.swap-cover-ph { display: flex; align-items: center; justify-content: center; background: #e5ddd0; font-size: 1.1rem; }
.swap-book-text { min-width: 0; }
.swap-title { font-weight: 600; overflow-wrap: anywhere; }
.swap-author { font-size: .85rem; color: var(--muted); }
.swap-book-gone { font-size: .85rem; color: #991b1b; font-style: italic; }
.swap-flag { display: inline-block; font-size: .7rem; font-weight: 600; padding: 0 6px; border-radius: 4px; background: #f1f5f9; color: #64748b; margin-left: 4px; }
.swap-flag-red { background: #fee2e2; color: #991b1b; }

/* datorā kartītes, telefonā saraksts ar "skatīt vairāk" (kā lietotājiem) */
.mob-only { display: none; }

/* lietotāju un žurnāla kartītes */
.user-item-active { border-left-color: #22c55e; }
.user-item-admin { border-left-color: #8b5cf6; }
.user-item-blocked { border-left-color: #ef4444; }
.user-badge-admin { background: #ede9fe; color: #5b21b6; }
.log-item-bad { border-left-color: #ef4444; }
.log-item-good { border-left-color: #22c55e; }

/* grāmatu tabs - tās pašas kartītes */
.book-item-Available { border-left-color: #22c55e; }
.book-item-Pending { border-left-color: #eab308; }
.book-item-UnderReview { border-left-color: #3b82f6; }
.book-badge-Available { background: #d1fae5; color: #166534; }
.book-badge-Pending { background: #fef9c3; color: #854d0e; }
.book-badge-UnderReview { background: #dbeafe; color: #1e40af; }
.book-stat-Available strong { color: #166534; }
.book-stat-Pending strong { color: #a16207; }
.book-stat-UnderReview strong { color: #1e40af; }
.book-sides { display: grid; grid-template-columns: 3fr 2fr; gap: 10px; }
.book-cover-lg { width: 56px; height: 80px; }
.book-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.book-chip { font-size: .72rem; padding: 1px 8px; border-radius: 999px; background: rgba(0, 0, 0, .06); }
.book-desc {
  margin-top: 6px !important;
  font-size: .82rem;
  color: var(--muted);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.book-facts { list-style: none; padding: 0; margin: 0; font-size: .85rem; display: flex; flex-direction: column; gap: 2px; }
.book-fact-pending { color: #a16207; font-weight: 600; }
.book-review-reason {
  margin-top: 8px !important;
  font-size: .82rem;
  padding: 6px 10px;
  border-left: 3px solid #3b82f6;
  background: rgba(59, 130, 246, .08);
  border-radius: 0 6px 6px 0;
  overflow-wrap: anywhere;
}

/* vērtējumu tabs - tās pašas kartītes, krāsa pēc zvaigznēm */
.rating-item-good { border-left-color: #22c55e; }
.rating-item-mid { border-left-color: #eab308; }
.rating-item-bad { border-left-color: #ef4444; }
.rating-stars { color: #f5a623; font-size: 1.05rem; letter-spacing: .08em; }
.rating-stars-off { color: #d6d3d1; }
.rating-badge-good { background: #d1fae5; color: #166534; }
.rating-badge-mid { background: #fef9c3; color: #854d0e; }
.rating-badge-bad { background: #fee2e2; color: #991b1b; }
.rating-review {
  margin: 0;
  padding: 6px 10px;
  border-left: 3px solid #f5a623;
  background: rgba(245, 166, 35, .08);
  border-radius: 0 6px 6px 0;
  font-style: italic;
  overflow-wrap: anywhere;
}
.rating-no-review { font-size: .85rem; color: var(--muted); font-style: italic; }
.rating-avg { cursor: default; }

.swap-foot { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 10px; font-size: .8rem; color: var(--muted); }
.swap-ratings { width: 100%; display: flex; flex-direction: column; gap: 2px; }
.swap-rating { color: inherit; }

@media (max-width: 575.98px) {
  .swap-sides, .book-sides { grid-template-columns: 1fr; }
  .swap-arrow { justify-self: center; transform: rotate(90deg); font-size: 1.1rem; }
  .swap-actions { margin-left: 0; width: 100%; }
}


.admin-section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.toggle-arrow:hover { background: #2d5630; }

.toggle-arrow {
  font-size: .7rem;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--color-primary, #3a6b3e);
  color: #fff;
  transition: transform .2s ease;
  flex-shrink: 0;
}

.toggle-arrow.rotated { transform: rotate(90deg); }

/* tumšais režīms */
:global([data-theme="dark"]) .admin-tab-btn { color: #9a9088; }
:global([data-theme="dark"]) .admin-tab-btn:hover { color: #e8e0d5; }
:global([data-theme="dark"]) .admin-tab-btn.active { color: #6aab6e; border-bottom-color: #6aab6e; }
:global([data-theme="dark"]) .admin-open-all-btn { color: #9a9088; border-color: #4a4038; }
:global([data-theme="dark"]) .admin-open-all-btn:hover { color: #e8e0d5; border-color: #9a9088; }
:global([data-theme="dark"]) .admin-open-all-btn.active { color: #6aab6e; border-color: #6aab6e; }
:global([data-theme="dark"]) .admin-tabs-wrap { border-bottom-color: #3a3228; }
:global([data-theme="dark"]) .admin-card { background: #211d18; border-color: #2e2820; }

.lang-editor-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.lang-editor-modal {
  background: var(--card-bg, #fff);
  border-radius: 12px;
  padding: 1.5rem;
  width: 100%;
  max-width: 760px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 32px rgba(0,0,0,.2);
}
.lang-editor-list {
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: .4rem;
}
.lang-editor-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .5rem;
  align-items: center;
}
.lang-editor-key {
  font-size: .75rem;
  font-family: monospace;
  color: var(--muted, #888);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.lang-editor-input { font-size: .8rem; }

[data-theme="dark"] .lang-editor-modal { background: #211d18; }

.log-action-badge {
  font-size: .75rem;
  font-weight: 600;
  background: var(--green-light, #e8f5e9);
  color: var(--green, #3a6b3e);
  padding: .15rem .5rem;
  border-radius: 999px;
  white-space: nowrap;
}

/* telefonā - tikai galvenais, pārējais pēc "Skatīt vairāk" */
.m-toggle, .m-filter-btn { display: none; }

@media (max-width: 767.98px) {
  .desk-only { display: none; }
  .mob-only { display: block; }

  /* tabi 2 rindās pa 4, bez scrollēšanas uz sāniem */
  .admin-tabs-wrap { border-bottom: none; }
  .admin-tabs {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
    width: 100%;
  }
  .admin-tab-btn {
    position: relative;
    margin: 0;
    padding: 8px 4px;
    min-height: 44px;
    font-size: .78rem;
    line-height: 1.15;
    text-align: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--card-bg, #fff);
  }
  /* izvēlētais - pilns zaļš, lai uzreiz redz kur atrodies */
  .admin-tab-btn.active {
    background: #3a6b3e;
    border-color: #3a6b3e;
    color: #fff;
    box-shadow: 0 2px 6px rgba(58, 107, 62, .35);
  }
  .admin-tab-badge { position: absolute; top: -6px; right: -4px; margin: 0; }

  .admin-tabs-controls { width: 100%; padding: 0; }
  .admin-open-all-btn { flex: 1; padding: 7px 12px; }

  .m-filter-btn { display: inline-block; }
  .filter-bar > :not(.m-keep):not(.m-filter-btn) { display: none; }
  .filter-bar.filters-open > :not(.m-keep):not(.m-filter-btn) { display: block; }
  .filter-bar .form-control-sm.m-keep { flex: 1; max-width: none; }
  .filter-bar.filters-open .form-select-sm { max-width: none; width: 100%; }

  .m-cards thead { display: none; }
  .m-cards, .m-cards tbody { display: block; width: 100%; }
  .m-cards tr {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 10px;
  }
  .m-cards td {
    border: none;
    padding: 0;
    text-align: left !important;
    background: transparent !important;
    box-shadow: none !important;
  }
  .m-cards td:not(.m-main):not(.m-toggle) { display: none; }

  /* galvenais pirmajā rindā, detaļas zem tā, pogas blakus */
  .m-cards tr.open td:not(.m-main):not(.m-toggle) { display: flex; gap: 8px; align-items: center; order: 1; width: 100%; }
  .m-cards tr.open td.ab-col:not(.m-main):not(.m-toggle) { width: auto; }
  .m-cards tr.open td.ab-col:empty { display: none; }
  .m-cards td.m-toggle { display: block; order: 2; width: 100%; }
  .m-cards td[data-label]::before {
    content: attr(data-label);
    min-width: 90px;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
  }
}
</style>

<style>
/* tumšais režīms - bez scoped, citādi [data-theme] nestrādā */
[data-theme="dark"] .admin-card {
  background: #211d18;
  box-shadow: 0 2px 8px rgba(0,0,0,.3);
}


[data-theme="dark"] .admin-card .table {
  --bs-table-color: #e8e0d5;
  --bs-table-bg: transparent;
  --bs-table-border-color: #2e2820;
  --bs-table-hover-bg: #2a2420;
  --bs-table-hover-color: #e8e0d5;
  --bs-table-striped-color: #e8e0d5;
  color: #e8e0d5 !important;
}

[data-theme="dark"] .admin-card .table th {
  color: #9a9088 !important;
  border-color: #2e2820 !important;
}

[data-theme="dark"] .admin-card .table td {
  color: #e8e0d5 !important;
  border-color: #2e2820 !important;
}

@media (max-width: 767.98px) {
  [data-theme="dark"] .admin-tabs .admin-tab-btn { background: #211d18; border-color: #3a3228; color: #c8c0b5; }
  [data-theme="dark"] .admin-tabs .admin-tab-btn.active { background: #4a8a4f; border-color: #4a8a4f; color: #fff; }
}

[data-theme="dark"] .admin-card .badge.bg-light {
  background: #2e2820 !important;
  color: #c8c0b5 !important;
}

/* apmaiņu tabs tumšajā režīmā */
[data-theme="dark"] .admin-card .swap-side { background: rgba(255, 255, 255, .04); }
[data-theme="dark"] .admin-card .swap-cover-ph { background: #3d3630; }
[data-theme="dark"] .admin-card .swap-badge-pending { background: #422006; color: #fde68a; }
[data-theme="dark"] .admin-card .swap-badge-accepted { background: #14532d; color: #bbf7d0; }
[data-theme="dark"] .admin-card .swap-badge-declined,
[data-theme="dark"] .admin-card .swap-flag { background: #2e2820; color: #c8c0b5; }
[data-theme="dark"] .admin-card .swap-flag-red { background: #450a0a; color: #fecaca; }
[data-theme="dark"] .admin-card .swap-stat-stale { background: #422006; color: #fde68a; }
[data-theme="dark"] .admin-card .swap-book-gone { color: #fca5a5; }
[data-theme="dark"] .admin-card .user-badge-admin { background: #2e1065; color: #ddd6fe; }
[data-theme="dark"] .admin-card .book-badge-Available { background: #14532d; color: #bbf7d0; }
[data-theme="dark"] .admin-card .book-badge-Pending { background: #422006; color: #fde68a; }
[data-theme="dark"] .admin-card .book-badge-UnderReview { background: #172554; color: #bfdbfe; }
[data-theme="dark"] .admin-card .book-chip { background: rgba(255, 255, 255, .08); }
[data-theme="dark"] .admin-card .book-review-reason { background: rgba(59, 130, 246, .12); }
[data-theme="dark"] .admin-card .rating-badge-good { background: #14532d; color: #bbf7d0; }
[data-theme="dark"] .admin-card .rating-badge-mid { background: #422006; color: #fde68a; }
[data-theme="dark"] .admin-card .rating-badge-bad { background: #450a0a; color: #fecaca; }
[data-theme="dark"] .admin-card .rating-stars-off { color: #57534e; }
[data-theme="dark"] .admin-card .rating-review { background: rgba(245, 166, 35, .1); }
</style>
