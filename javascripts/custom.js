if (!document.location.search.startsWith('?BazaR') && !document.location.search.includes('/iframe')) {
    barba.init({
        prevent: ({ el }) => $(el).is('.include, .podcast-btn, .modalbox, .prevent-barba') || $(el).closest('.BAZ_actions_fiche').length > 0
    }); // barbajs load asynchronoulsy every page in the body section so it does not reload the full page, and the player is not interrupted
}

$(document).ready(function() {
    // By default display live radio player
    showLiveRadio()

    $(document).on('click', '.podcast-btn', function(e) {
        e.preventDefault()
        var podcastName = this.href;
        if (podcastName.includes('https://www.mixcloud.com/')) {
            podcastName = podcastName.split('https://www.mixcloud.com')[1]
        }
        var src = "https://www.mixcloud.com/widget/iframe/?hide_cover=1&light=0&autoplay=1&feed=" + podcastName
        var podcastIframe = $(`<button class="btn btn-primary listen-live-btn">Ecouter le direct</button>
                               <iframe id="mixcloud-iframe" width="100%" height="120" src="${src}" frameborder="0" allow="autoplay"></iframe>`)
        $('.sound-player').empty().append(podcastIframe);      
        
        $('.listen-live-btn').click(function() {
            showLiveRadio()
        })
    })    
})

function showLiveRadio() {
    var liveIframe = $('<iframe id="radioking-iframe" src="https://www.radioking.com/widgets/player/player.php?id=3046&c=%232F3542&c2=%23D1D1D1&f=h&i=0&ii=null&p=1&s=0&li=0&popup=0&plc=NaN&h=undefined&l=500&a=0&v=2" style="border-radius: 0; width: 100%; height: 150px; min-width: 470px; min-height: 0; " frameBorder="0" ></iframe>')
    $('.sound-player').empty().append(liveIframe);
}