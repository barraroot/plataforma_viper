<template>
    <div class="item-game text-gray-700 w-full h-auto mr-4 cursor-pointer">
        <a href="#" v-if="game.technology === 'aposta'" @click.prevent="jogoCasa(game)">
            <img :src="`/storage/` + game.cover" alt="" class="w-full">
        </a>
        <RouterLink
            v-else-if="game.distribution === 'kagaming' || (game.distribution === 'games_api' && game.provider_id == 1)"
            :to="{ name: 'casinoPlayPage', params: { id: game.id, slug: game.game_code } }">
            <img :src="game.cover" alt="" class="w-full">
        </RouterLink>
        <RouterLink v-else :to="{ name: 'casinoPlayPage', params: { id: game.id, slug: game.game_code } }">
            <img :src="`/storage/` + game.cover" alt="" class="w-full ">
        </RouterLink>
        <div class="flex justify-between w-full text-gray-700 dark:text-gray-400 px-3 py-2">
            <div class="flex flex-col justify-start items-start">
                <span class="truncate text-[12px]">{{ game.game_name }}</span>
                <small class="truncate text-[10px]">{{ game?.provider?.name }}</small>
            </div>
            <button type="button">
                <img :src="`/assets/images/icons/info-game.svg`" alt="" width="29">
            </button>
        </div>
    </div>
</template>


<script>
import {RouterLink} from "vue-router";
import Swal from 'sweetalert2'
import {useWalletStore} from '@/Stores/Wallet'
import {useAuthStore} from "@/Stores/Auth.js";
import {useToast} from "vue-toastification";

export default {
    props: ['index', 'game'],
    components: {RouterLink},
    data() {
        return {
            isLoading: false,
            modalGame: null,
        }
    },
    setup(props) {


        return {};
    },
    computed: {},
    mounted() {
    },
    methods: {
        jogoCasa(game) {
            window.localStorage.setItem("game", null);
            const walletStore = useWalletStore();
            var balance = parseInt(walletStore.wallet.total_balance);
            Swal.fire({
                title: "Valor da aposta?",
                html: `
                <input
                  type="number"
                  value="1"
                  step="1"
                  class="input-value-range"
                  id="range-value">`,
                input: "range",
                color: "#fff",
                confirmButtonColor: "var(--ci-primary-color)",
                confirmButtonText: "Jogar",
                inputAttributes: {
                    min: "1",
                    max: balance,
                    step: "1"
                },
                background: game.background_image ? "url(/storage/" + game.background_image + ")" : "",
                width: '512px',
                inputValue: 1,
                didOpen: () => {
                    const inputRange = Swal.getInput();
                    const inputNumber = Swal.getPopup().querySelector('#range-value')
                    const container = Swal.getPopup().querySelector('.swal2-range')
                    const htmlContainer = Swal.getPopup();
                    container.style.background = 'transparent';
                    htmlContainer.style.height = '512px';
                    // remove default output
                    Swal.getPopup().querySelector('output').style.display = 'none'
                    inputRange.style.width = '100%'
                    inputRange.style.backgroundColor = 'transparent';

                    // sync input[type=number] with input[type=range]
                    inputRange.addEventListener('input', () => {
                        inputNumber.value = inputRange.value
                        inputRange.style.backgroundColor = 'transparent';
                    })

                    // sync input[type=range] with input[type=number]
                    inputNumber.addEventListener('change', () => {
                        inputRange.value = inputNumber.value
                    })
                }
            })
                .then((result) => {

                    if (result.isDismissed){
                        window.localStorage.setItem("game", null);
                    }
                    /* Read more about isConfirmed, isDenied below */
                    if (result.isConfirmed) {
                        let newGame = JSON.stringify(game);
                        window.localStorage.setItem("game", newGame);
                        const valorAposta = result.value;
                        this.$router.push({
                            name: 'casinoPlayPage',
                            params: {id: game.id, slug: game.game_code},
                            query: {valor: valorAposta}
                        });
                    } else if (result.isDenied) {
                        Swal.fire("Changes are not saved", "", "info");
                    }
                });
        }
    },
    created() {
        const url = new URL(window.location.href);
        var message = url.searchParams.get('message');
        var type = url.searchParams.get('type');
        const _toast = useToast();

        if (type === 'error') {
            _toast.error(this.$t(message));
        }
        if (!type && message !== null) {
            _toast.success(this.$t(message));
        }


        history.replaceState(null, null, window.location.pathname);
        var game = JSON.parse(window.localStorage.getItem("game"));
        if (
            game
            && this.$props.game.game_id === game.game_id
        ) {
            this.jogoCasa(game);
        }
    },
    watch: {},
};
</script>

<style scoped></style>
