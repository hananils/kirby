<template>
	<section
		:data-selected="isSelected"
		class="k-layout"
		tabindex="0"
		@click="$emit('select')"
	>
		<k-grid class="k-layout-columns">
			<k-layout-column
				v-for="(column, columnIndex) in columns"
				:key="column.id"
				:endpoints="endpoints"
				:fieldset-groups="fieldsetGroups"
				:fieldsets="fieldsets"
				v-bind="column"
				@input="
					$emit('updateColumn', {
						column,
						columnIndex,
						blocks: $event
					})
				"
			/>
		</k-grid>
		<nav v-if="!disabled" class="k-layout-toolbar">
			<k-button
				v-if="settings"
				:title="$t('settings')"
				class="k-layout-toolbar-button"
				icon="settings"
				@click="openSettings"
			/>

			<k-button
				class="k-layout-toolbar-button"
				icon="angle-down"
				@click="$refs.options.toggle()"
			/>
			<k-dropdown-content ref="options" :options="options" align-x="end" />
			<k-sort-handle />
		</nav>
	</section>
</template>

<script>
/**
 * @internal
 */
export default {
	props: {
		attrs: [Array, Object],
		columns: Array,
		disabled: Boolean,
		endpoints: Object,
		fieldsetGroups: Object,
		fieldsets: Object,
		id: String,
		isSelected: Boolean,
		layouts: Array,
		settings: Object
	},
	computed: {
		options() {
			return [
				{
					click: () => this.$emit("prepend"),
					icon: "angle-up",
					text: this.$t("insert.before")
				},
				{
					click: () => this.$emit("append"),
					icon: "angle-down",
					text: this.$t("insert.after")
				},
				"-",
				{
					click: () => this.openSettings(),
					icon: "settings",
					text: this.$t("settings"),
					when: this.$helper.object.isEmpty(this.settings) === false
				},
				{
					click: () => this.$emit("duplicate"),
					icon: "copy",
					text: this.$t("duplicate")
				},
				{
					click: () => this.$emit("change"),
					disabled: this.layouts.length === 1,
					icon: "dashboard",
					text: this.$t("field.layout.change")
				},
				"-",
				{
					click: () => this.$emit("copy"),
					icon: "template",
					text: this.$t("copy")
				},
				{
					click: () => this.$emit("paste"),
					icon: "download",
					text: this.$t("paste.after")
				},
				"-",
				{
					click: () => this.remove(),
					icon: "trash",
					text: this.$t("field.layout.delete")
				}
			];
		},
		tabs() {
			let tabs = this.settings.tabs;

			for (const [tabName, tab] of Object.entries(tabs)) {
				for (const fieldName in tab.fields) {
					tabs[tabName].fields[fieldName].endpoints = {
						field: this.endpoints.field + "/fields/" + fieldName,
						section: this.endpoints.section,
						model: this.endpoints.model
					};
				}
			}

			return tabs;
		}
	},
	methods: {
		openSettings() {
			this.$panel.drawer.open({
				component: "k-form-drawer",
				props: {
					icon: "settings",
					tabs: this.tabs,
					title: this.$t("settings"),
					value: this.attrs
				},
				on: {
					input: (attrs) => this.$emit("updateAttrs", attrs)
				}
			});
		},
		remove() {
			this.$panel.dialog.open({
				component: "k-remove-dialog",
				props: {
					text: this.$t("field.layout.delete.confirm")
				},
				on: {
					submit: () => {
						this.$emit("remove");
						this.$panel.dialog.close();
					}
				}
			});
		}
	}
};
</script>

<style>
.k-layout {
	--layout-border-color: var(--color-gray-300);
	--layout-toolbar-width: 2rem;

	position: relative;
	padding-inline-end: var(--layout-toolbar-width);
	background: #fff;
	box-shadow: var(--shadow);
}
[data-disabled="true"] .k-layout {
	padding-inline-end: 0;
}
.k-layout:not(:last-of-type) {
	margin-bottom: 1px;
}
.k-layout:focus {
	outline: 0;
}
/** Toolbar **/
.k-layout-toolbar {
	position: absolute;
	inset-block: 0;
	inset-inline-end: 0;
	width: var(--layout-toolbar-width);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: space-between;
	padding-bottom: var(--spacing-2);
	font-size: var(--text-sm);
	background: var(--color-gray-100);
	border-inline-start: 1px solid var(--color-light);
	color: var(--color-gray-500);
}
.k-layout-toolbar:hover {
	color: var(--color-black);
}
.k-layout-toolbar-button {
	width: var(--layout-toolbar-width);
	height: var(--layout-toolbar-width);
}

/** Columns **/
.k-layout-columns.k-grid {
	grid-gap: 1px;
	background: var(--layout-border-color);
	background: var(--color-gray-300);
}
.k-layout:not(:first-child) .k-layout-columns.k-grid {
	border-top: 0;
}
</style>
