<template>
	<k-field v-bind="$props" class="k-layout-field">
		<template v-if="!disabled && hasFieldsets" #options>
			<k-button-group layout="collapsed">
				<k-button
					:autofocus="autofocus"
					:text="$t('add')"
					icon="add"
					variant="filled"
					size="xs"
					@click="$refs.layouts.select(0)"
				/>
				<k-button
					icon="dots"
					variant="filled"
					size="xs"
					@click="$refs.options.toggle()"
				/>
				<k-dropdown-content ref="options" :options="options" align-x="end" />
			</k-button-group>
		</template>

		<k-layouts ref="layouts" v-bind="$props" @input="$emit('input', $event)" />

		<footer v-if="!disabled && hasFieldsets">
			<k-button
				:title="$t('add')"
				icon="add"
				size="xs"
				variant="filled"
				@click="$refs.layouts.select(value.length)"
			/>
		</footer>
	</k-field>
</template>

<script>
import { props as Field } from "../Field.vue";

export default {
	mixins: [Field],
	inheritAttrs: false,
	props: {
		autofocus: Boolean,
		empty: String,
		fieldsetGroups: Object,
		fieldsets: Object,
		layouts: {
			type: Array,
			default: () => [["1/1"]]
		},
		selector: Object,
		settings: Object,
		value: {
			type: Array,
			default: () => []
		}
	},
	computed: {
		hasFieldsets() {
			return this.$helper.object.length(this.fieldsets) > 0;
		},
		isEmpty() {
			return this.value.length === 0;
		},
		options() {
			return [
				{
					click: () => this.$refs.layouts.copy(),
					disabled: this.isEmpty,
					icon: "template",
					text: this.$t("copy.all")
				},
				{
					click: () => this.$refs.layouts.pasteboard(),
					icon: "download",
					text: this.$t("paste")
				},
				"-",
				{
					click: () => this.$refs.layouts.removeAll(),
					disabled: this.isEmpty,
					icon: "trash",
					text: this.$t("delete.all")
				}
			];
		}
	}
};
</script>

<style>
/** TODO: .k-layout-field > :has(+ footer) { margin-bottom: var(--spacing-3);} */
.k-layout-field > footer {
	display: flex;
	justify-content: center;
	margin-top: var(--spacing-3);
}
</style>
