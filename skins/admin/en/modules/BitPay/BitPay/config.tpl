<div class="bitpay configured">

  <table cellspacing="2" cellpadding="5" class="settings-table">
    <tr>
      <td>
        <label for="settings_riskSpeed">{t(#Risk/Speed#)}</label>
      </td>
      <td>
        <select name="settings[riskSpeed]" id="settings_prefix">
          <option id="settings_prefix" {if:paymentMethod.getSetting(#riskSpeed#)=#low#}selected{end:}>low</option>
          <option id="settings_prefix" {if:paymentMethod.getSetting(#riskSpeed#)=#medium#}selected{end:}>medium</option>
          <option id="settings_prefix" {if:paymentMethod.getSetting(#riskSpeed#)=#high#}selected{end:}>high</option>
        </select>
      </td>
    </tr>

<!--     <tr>
      <td>
        <label for="settings_redirectURL">{t(#Redirect URL#)}</label>
      </td>
      <td>
        <input type="text" id="settings_prefix" name="settings[redirectURL]" value="{paymentMethod.getSetting(#redirectURL#)}" />
      </td>
    </tr> -->

    <tr>
      <td>
        <label for="settings_debug">{t(#Debug#)}</label>
      </td>
      <td>
        <select name="settings[debug]" id="settings_prefix">
          <option id="settings_prefix" {if:paymentMethod.getSetting(#debug#)=#true#}selected{end:} value="true">on</option>
          <option id="settings_prefix" {if:paymentMethod.getSetting(#debug#)=#false#}selected{end:} value="false">off</option>
        </select>
      </td>
    </tr>

    <tr>
      <td>
        <label for="settings_version">{t(#Version#)}</label>
      </td>
      <td>
        {paymentMethod.getSetting(#version#)}
      </td>
    </tr>

    <tr>
      <td>
        <div class="buttons">
          <button type="submit" class="btn regular-button">
            <span>Update</span>
          </button>
        </div>
      </td>
    </tr>

  </table>

</div>

</form>

<br />

<div class="pairing">
  <table>

    <tr {if:!paymentMethod.getSetting(#connection#)=#connected#}style="display:none;"{end:} id="paired">
        <div {if:!paymentMethod.getSetting(#connection#)=#connected#}style="display:none;"{end:} id="paired" class="alert alert-success" style="display: inline-block;"> Paired with <b>{paymentMethod.getSetting(#network#)}</b></div>
    </tr>
    <tr>
      <form id="pair">

        <td>
          <label for="settings_network">{t(#Pair with BitPay#)}</label>
        </td>

        <td>
          <select id="network" name="network">
            <option value="testnet">test</option>
            <option value="livenet">live</option>
          </select>
        </td>

        <td>
          <button type="submit">Connect with BitPay</button>
        </td>

      </form>
      <div id="ajaxLoader" style="display: none;">Connecting...</div>
    </tr>
  </table>
</div>

<!-- Hide useless update button -->
<style>
button.btn.regular-button.submit {
display: none;
}
</style>

<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<script type="text/javascript" >

core.bind(
  'connectionState',
  function (event, state) {
    console.log("ConnectionState: " + state);
  }
);

core.post(
  URLHandler.buildURL({
    target: 'bitpay_ajax'
  }),
  function(request) {
    console.log('Ajax request', request);
  },
  {
    endpoint: 'connect'
  }
);

// Variable to hold request
var request;

// Bind to the submit event of our form
$("#pair").submit(function(event){

    $(this).parent().hide();
    document.getElementById("ajaxLoader").style.display = "";
    document.getElementById("paired").style.display = "none";

    console.log('Trying to pair');

    // Abort any pending request
    if (request) {
        request.abort();
    }

    core.bind(
      'generatedConnectUrl',
      function (event, url) {
        document.getElementById("ajaxLoader").style.display = "none";
        document.getElementById("pair").style.display = "";
        window.location = url;
      }
    );

    var network = $('#network').val();

    core.post(
      URLHandler.buildURL({
        target: 'bitpay_ajax'
      }),
      function(request) {
        console.log('Ajax request', request);
      },
      {
        endpoint: '',
        network: network,
        redirecturl: document.URL,
        method_id: '{t(paymentMethod.method_id)}'
      }
    );

    event.preventDefault();

});
</script>