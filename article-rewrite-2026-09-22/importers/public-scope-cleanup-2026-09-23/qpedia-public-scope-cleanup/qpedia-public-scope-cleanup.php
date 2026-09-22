<?php
/**
 * Plugin Name: Qpedia Public Scope Cleanup
 * Description: Permanently removes the 118 audited out-of-scope quantum articles and preserves 55 merge redirects. The 8 scientist articles are explicitly untouched.
 * Version: 1.0.0
 * Author: Qpedia
 */
if (!defined('ABSPATH')) exit;
final class Qpedia_Public_Scope_Cleanup {
    const OPTION = 'qpedia_public_scope_cleanup_state_v1';
    const NONCE = 'qpedia_public_scope_cleanup_execute';
    private static function removals() { return array(
        array('id'=>2243,'slug'=>'stimulated-emission','sequence'=>43,'decision'=>'ادغام موضوعی'),
        array('id'=>2492,'slug'=>'quantum-battery','sequence'=>57,'decision'=>'ادغام موضوعی'),
        array('id'=>2744,'slug'=>'mitochondria-proton-tunneling','sequence'=>92,'decision'=>'ادغام موضوعی'),
        array('id'=>2745,'slug'=>'quantum-long-term-memory','sequence'=>93,'decision'=>'ادغام موضوعی'),
        array('id'=>2746,'slug'=>'dna-repair-enzymes','sequence'=>94,'decision'=>'ادغام موضوعی'),
        array('id'=>2752,'slug'=>'qubit-types-compared','sequence'=>99,'decision'=>'ادغام موضوعی'),
        array('id'=>2753,'slug'=>'quantum-repeater','sequence'=>100,'decision'=>'ادغام موضوعی'),
        array('id'=>2754,'slug'=>'bb84-protocol','sequence'=>101,'decision'=>'ادغام موضوعی'),
        array('id'=>2756,'slug'=>'hhl-algorithm','sequence'=>103,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2758,'slug'=>'lamb-shift','sequence'=>105,'decision'=>'ادغام موضوعی'),
        array('id'=>2759,'slug'=>'topological-superconductivity','sequence'=>106,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2761,'slug'=>'transactional-interpretation','sequence'=>107,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2762,'slug'=>'qbism','sequence'=>108,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2763,'slug'=>'objective-collapse','sequence'=>109,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2838,'slug'=>'pauli-exclusion','sequence'=>114,'decision'=>'ادغام تکراری'),
        array('id'=>2844,'slug'=>'grw-collapse','sequence'=>116,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2851,'slug'=>'quantum-realism','sequence'=>117,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2862,'slug'=>'quantum-memory','sequence'=>119,'decision'=>'ادغام موضوعی'),
        array('id'=>318,'slug'=>'bird-quantum-compass','sequence'=>122,'decision'=>'ادغام موضوعی'),
        array('id'=>319,'slug'=>'quantum-smell','sequence'=>123,'decision'=>'ادغام موضوعی'),
        array('id'=>569,'slug'=>'superposition-explained','sequence'=>136,'decision'=>'ادغام تکراری'),
        array('id'=>3569,'slug'=>'quantum-fluctuation-theorem','sequence'=>140,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2870,'slug'=>'quantum-chemistry-drug-discovery','sequence'=>162,'decision'=>'ادغام موضوعی'),
        array('id'=>3323,'slug'=>'single-photon-source','sequence'=>166,'decision'=>'ادغام موضوعی'),
        array('id'=>3325,'slug'=>'entangled-photon-source','sequence'=>167,'decision'=>'ادغام موضوعی'),
        array('id'=>3327,'slug'=>'spontaneous-parametric-down-conversion','sequence'=>168,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3330,'slug'=>'photon-counting','sequence'=>169,'decision'=>'ادغام موضوعی'),
        array('id'=>3332,'slug'=>'photon-blockade','sequence'=>170,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3338,'slug'=>'quantum-secure-direct-communication','sequence'=>173,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3342,'slug'=>'quantum-auction','sequence'=>175,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3344,'slug'=>'quantum-fpga','sequence'=>176,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3346,'slug'=>'cryogenic-electronics','sequence'=>177,'decision'=>'ادغام موضوعی'),
        array('id'=>3350,'slug'=>'quantum-dimer','sequence'=>179,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3352,'slug'=>'nanowire','sequence'=>180,'decision'=>'ادغام موضوعی'),
        array('id'=>3354,'slug'=>'dipolar-gas','sequence'=>181,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3356,'slug'=>'fermi-gas','sequence'=>182,'decision'=>'ادغام موضوعی'),
        array('id'=>3358,'slug'=>'quantum-droplet','sequence'=>183,'decision'=>'ادغام موضوعی'),
        array('id'=>3360,'slug'=>'soliton','sequence'=>184,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3362,'slug'=>'quantum-pigeonhole','sequence'=>185,'decision'=>'ادغام موضوعی'),
        array('id'=>3364,'slug'=>'quantum-solipsism','sequence'=>186,'decision'=>'ادغام موضوعی'),
        array('id'=>3367,'slug'=>'proton-decay','sequence'=>187,'decision'=>'ادغام موضوعی'),
        array('id'=>3385,'slug'=>'quantum-blockchain','sequence'=>196,'decision'=>'ادغام موضوعی'),
        array('id'=>3397,'slug'=>'coherent-states','sequence'=>202,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3399,'slug'=>'fock-state','sequence'=>203,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3401,'slug'=>'four-wave-mixing','sequence'=>204,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3403,'slug'=>'optical-parametric-oscillator','sequence'=>205,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3405,'slug'=>'homodyne-detection','sequence'=>206,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3408,'slug'=>'quantum-thermometry','sequence'=>207,'decision'=>'ادغام موضوعی'),
        array('id'=>3410,'slug'=>'quantum-engine-efficiency','sequence'=>208,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3412,'slug'=>'quantum-cell-signaling','sequence'=>209,'decision'=>'ادغام موضوعی'),
        array('id'=>3414,'slug'=>'quantum-anesthesia','sequence'=>210,'decision'=>'ادغام موضوعی'),
        array('id'=>3422,'slug'=>'ghz-state','sequence'=>214,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3424,'slug'=>'hardy-paradox','sequence'=>215,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3426,'slug'=>'quantum-contextuality','sequence'=>216,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3428,'slug'=>'quantum-cheshire-cat','sequence'=>217,'decision'=>'ادغام موضوعی'),
        array('id'=>3430,'slug'=>'three-box-paradox','sequence'=>218,'decision'=>'ادغام موضوعی'),
        array('id'=>3432,'slug'=>'quantum-logic','sequence'=>219,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3438,'slug'=>'anomalous-magnetic-moment','sequence'=>222,'decision'=>'ادغام موضوعی'),
        array('id'=>3440,'slug'=>'fine-structure-constant','sequence'=>223,'decision'=>'ادغام موضوعی'),
        array('id'=>3443,'slug'=>'quantum-chromodynamics','sequence'=>225,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3445,'slug'=>'electroweak-theory','sequence'=>226,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3450,'slug'=>'quantum-phase-estimation','sequence'=>227,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3452,'slug'=>'quantum-walk','sequence'=>228,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3454,'slug'=>'variational-quantum-eigensolver','sequence'=>229,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3456,'slug'=>'qaoa','sequence'=>230,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3458,'slug'=>'quantum-kernel','sequence'=>231,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3460,'slug'=>'quantum-neural-network','sequence'=>232,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3462,'slug'=>'quantum-generative-model','sequence'=>233,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3464,'slug'=>'quantum-reinforcement-learning','sequence'=>234,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3466,'slug'=>'quantum-programming-language','sequence'=>235,'decision'=>'ادغام موضوعی'),
        array('id'=>3468,'slug'=>'quantum-compiler','sequence'=>236,'decision'=>'ادغام موضوعی'),
        array('id'=>3470,'slug'=>'quantum-control','sequence'=>237,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3474,'slug'=>'weyl-semimetal','sequence'=>239,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3476,'slug'=>'dirac-semimetal','sequence'=>240,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3478,'slug'=>'majorana-fermion','sequence'=>241,'decision'=>'ادغام موضوعی'),
        array('id'=>3480,'slug'=>'anyon','sequence'=>242,'decision'=>'ادغام موضوعی'),
        array('id'=>3482,'slug'=>'fractional-quantum-hall-effect','sequence'=>243,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3484,'slug'=>'mott-insulator','sequence'=>244,'decision'=>'ادغام موضوعی'),
        array('id'=>3486,'slug'=>'quantum-criticality','sequence'=>245,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3488,'slug'=>'quantum-phase-transition','sequence'=>246,'decision'=>'ادغام موضوعی'),
        array('id'=>3491,'slug'=>'cavity-qed','sequence'=>247,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3493,'slug'=>'circuit-qed','sequence'=>248,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3495,'slug'=>'rabi-oscillations','sequence'=>249,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3497,'slug'=>'electromagnetically-induced-transparency','sequence'=>250,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3499,'slug'=>'quantum-imaging-undetected-photons','sequence'=>251,'decision'=>'ادغام موضوعی'),
        array('id'=>3503,'slug'=>'super-resolution-quantum','sequence'=>253,'decision'=>'ادغام موضوعی'),
        array('id'=>3505,'slug'=>'quantum-secret-sharing','sequence'=>254,'decision'=>'ادغام موضوعی'),
        array('id'=>3507,'slug'=>'device-independent-qkd','sequence'=>255,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3509,'slug'=>'measurement-device-independent-qkd','sequence'=>256,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3511,'slug'=>'twin-field-qkd','sequence'=>257,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3513,'slug'=>'quantum-digital-signature','sequence'=>258,'decision'=>'ادغام موضوعی'),
        array('id'=>3515,'slug'=>'quantum-money','sequence'=>259,'decision'=>'ادغام موضوعی'),
        array('id'=>3517,'slug'=>'quantum-voting','sequence'=>260,'decision'=>'ادغام موضوعی'),
        array('id'=>3519,'slug'=>'quantum-router','sequence'=>261,'decision'=>'ادغام موضوعی'),
        array('id'=>3525,'slug'=>'deutsch-jozsa-algorithm','sequence'=>264,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3527,'slug'=>'simon-algorithm','sequence'=>265,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3528,'slug'=>'bernstein-vazirani-algorithm','sequence'=>266,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3531,'slug'=>'heavy-fermion','sequence'=>267,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3533,'slug'=>'kondo-effect','sequence'=>268,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3535,'slug'=>'spin-ice','sequence'=>269,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3537,'slug'=>'squid','sequence'=>270,'decision'=>'ادغام موضوعی'),
        array('id'=>3539,'slug'=>'optical-lattice','sequence'=>271,'decision'=>'ادغام موضوعی'),
        array('id'=>3541,'slug'=>'rydberg-atom','sequence'=>272,'decision'=>'ادغام موضوعی'),
        array('id'=>3543,'slug'=>'polaron','sequence'=>273,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3545,'slug'=>'exciton','sequence'=>274,'decision'=>'ادغام موضوعی'),
        array('id'=>3547,'slug'=>'polariton','sequence'=>275,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3549,'slug'=>'magnon','sequence'=>276,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3551,'slug'=>'plasmon','sequence'=>277,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3553,'slug'=>'phonon','sequence'=>278,'decision'=>'ادغام موضوعی'),
        array('id'=>3555,'slug'=>'quantum-heat-engine','sequence'=>279,'decision'=>'ادغام موضوعی'),
        array('id'=>3557,'slug'=>'quantum-refrigerator','sequence'=>280,'decision'=>'ادغام موضوعی'),
        array('id'=>562,'slug'=>'brain-quantum-phenomena','sequence'=>282,'decision'=>'ادغام تکراری'),
        array('id'=>3559,'slug'=>'quantum-maxwell-demon','sequence'=>296,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3561,'slug'=>'quantum-entropy','sequence'=>297,'decision'=>'ادغام موضوعی'),
        array('id'=>3563,'slug'=>'quantum-thermalization','sequence'=>298,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3565,'slug'=>'many-body-localization','sequence'=>299,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>3567,'slug'=>'eigenstate-thermalization','sequence'=>300,'decision'=>'حذف از برنامه عمومی'),
        array('id'=>2760,'slug'=>'continuous-variable-teleportation','sequence'=>310,'decision'=>'حذف از برنامه عمومی')
    ); }
    private static function redirects() { return array(
        '/stimulated-emission/' => 'https://qpedia.ir/how-lasers-work/',
        '/quantum-battery/' => 'https://qpedia.ir/quantum-thermodynamics/',
        '/mitochondria-proton-tunneling/' => 'https://qpedia.ir/enzyme-quantum-tunneling/',
        '/quantum-long-term-memory/' => 'https://qpedia.ir/is-the-brain-quantum/',
        '/dna-repair-enzymes/' => 'https://qpedia.ir/genetic-mutation/',
        '/qubit-types-compared/' => 'https://qpedia.ir/qubit/',
        '/quantum-repeater/' => 'https://qpedia.ir/quantum-network/',
        '/bb84-protocol/' => 'https://qpedia.ir/quantum-cryptography-internet-security/',
        '/lamb-shift/' => 'https://qpedia.ir/quantum-electrodynamics/',
        '/pauli-exclusion/' => 'https://qpedia.ir/pauli-exclusion-principle/',
        '/quantum-memory/' => 'https://qpedia.ir/quantum-network/',
        '/bird-quantum-compass/' => 'https://qpedia.ir/quantum-bacteria/',
        '/quantum-smell/' => 'https://qpedia.ir/quantum-chemistry/',
        '/superposition-explained/' => 'https://qpedia.ir/quantum-superposition/',
        '/quantum-chemistry-drug-discovery/' => 'https://qpedia.ir/quantum-chemistry/',
        '/single-photon-source/' => 'https://qpedia.ir/photon/',
        '/entangled-photon-source/' => 'https://qpedia.ir/quantum-entanglement-explained/',
        '/photon-counting/' => 'https://qpedia.ir/photon/',
        '/cryogenic-electronics/' => 'https://qpedia.ir/dilution-refrigerator/',
        '/nanowire/' => 'https://qpedia.ir/transistor-quantum/',
        '/fermi-gas/' => 'https://qpedia.ir/bose-einstein-condensate/',
        '/quantum-droplet/' => 'https://qpedia.ir/superfluidity/',
        '/quantum-pigeonhole/' => 'https://qpedia.ir/schrodinger-cat/',
        '/quantum-solipsism/' => 'https://qpedia.ir/quantum-interpretation-debate/',
        '/proton-decay/' => 'https://qpedia.ir/proton-neutron-quark-structure/',
        '/quantum-blockchain/' => 'https://qpedia.ir/quantum-cryptography-internet-security/',
        '/quantum-thermometry/' => 'https://qpedia.ir/quantum-sensors/',
        '/quantum-cell-signaling/' => 'https://qpedia.ir/quantum-bacteria/',
        '/quantum-anesthesia/' => 'https://qpedia.ir/is-the-brain-quantum/',
        '/quantum-cheshire-cat/' => 'https://qpedia.ir/schrodinger-cat/',
        '/three-box-paradox/' => 'https://qpedia.ir/schrodinger-cat/',
        '/anomalous-magnetic-moment/' => 'https://qpedia.ir/electron/',
        '/fine-structure-constant/' => 'https://qpedia.ir/quantum-electrodynamics/',
        '/quantum-programming-language/' => 'https://qpedia.ir/quantum-computer-reality/',
        '/quantum-compiler/' => 'https://qpedia.ir/quantum-computer-reality/',
        '/majorana-fermion/' => 'https://qpedia.ir/standard-model/',
        '/anyon/' => 'https://qpedia.ir/standard-model/',
        '/mott-insulator/' => 'https://qpedia.ir/topological-insulator/',
        '/quantum-phase-transition/' => 'https://qpedia.ir/time-crystal/',
        '/quantum-imaging-undetected-photons/' => 'https://qpedia.ir/quantum-microscopy/',
        '/super-resolution-quantum/' => 'https://qpedia.ir/quantum-microscopy/',
        '/quantum-secret-sharing/' => 'https://qpedia.ir/quantum-cryptography-internet-security/',
        '/quantum-digital-signature/' => 'https://qpedia.ir/quantum-cryptography-internet-security/',
        '/quantum-money/' => 'https://qpedia.ir/quantum-cryptography-internet-security/',
        '/quantum-voting/' => 'https://qpedia.ir/quantum-cryptography-internet-security/',
        '/quantum-router/' => 'https://qpedia.ir/quantum-network/',
        '/squid/' => 'https://qpedia.ir/quantum-sensors/',
        '/optical-lattice/' => 'https://qpedia.ir/bose-einstein-condensate/',
        '/rydberg-atom/' => 'https://qpedia.ir/quantum-number/',
        '/exciton/' => 'https://qpedia.ir/quantum-dots-displays/',
        '/phonon/' => 'https://qpedia.ir/quantum-chemistry/',
        '/quantum-heat-engine/' => 'https://qpedia.ir/quantum-thermodynamics/',
        '/quantum-refrigerator/' => 'https://qpedia.ir/quantum-thermodynamics/',
        '/brain-quantum-phenomena/' => 'https://qpedia.ir/is-the-brain-quantum/',
        '/quantum-entropy/' => 'https://qpedia.ir/quantum-thermodynamics/'
    ); }
    private static function gone() { return array(
        '/hhl-algorithm/',
        '/topological-superconductivity/',
        '/transactional-interpretation/',
        '/qbism/',
        '/objective-collapse/',
        '/grw-collapse/',
        '/quantum-realism/',
        '/quantum-fluctuation-theorem/',
        '/spontaneous-parametric-down-conversion/',
        '/photon-blockade/',
        '/quantum-secure-direct-communication/',
        '/quantum-auction/',
        '/quantum-fpga/',
        '/quantum-dimer/',
        '/dipolar-gas/',
        '/soliton/',
        '/coherent-states/',
        '/fock-state/',
        '/four-wave-mixing/',
        '/optical-parametric-oscillator/',
        '/homodyne-detection/',
        '/quantum-engine-efficiency/',
        '/ghz-state/',
        '/hardy-paradox/',
        '/quantum-contextuality/',
        '/quantum-logic/',
        '/quantum-chromodynamics/',
        '/electroweak-theory/',
        '/quantum-phase-estimation/',
        '/quantum-walk/',
        '/variational-quantum-eigensolver/',
        '/qaoa/',
        '/quantum-kernel/',
        '/quantum-neural-network/',
        '/quantum-generative-model/',
        '/quantum-reinforcement-learning/',
        '/quantum-control/',
        '/weyl-semimetal/',
        '/dirac-semimetal/',
        '/fractional-quantum-hall-effect/',
        '/quantum-criticality/',
        '/cavity-qed/',
        '/circuit-qed/',
        '/rabi-oscillations/',
        '/electromagnetically-induced-transparency/',
        '/device-independent-qkd/',
        '/measurement-device-independent-qkd/',
        '/twin-field-qkd/',
        '/deutsch-jozsa-algorithm/',
        '/simon-algorithm/',
        '/bernstein-vazirani-algorithm/',
        '/heavy-fermion/',
        '/kondo-effect/',
        '/spin-ice/',
        '/polaron/',
        '/polariton/',
        '/magnon/',
        '/plasmon/',
        '/quantum-maxwell-demon/',
        '/quantum-thermalization/',
        '/many-body-localization/',
        '/eigenstate-thermalization/',
        '/continuous-variable-teleportation/'
    ); }
    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_qpedia_scope_cleanup', array(__CLASS__, 'execute'));
        add_action('template_redirect', array(__CLASS__, 'route_removed_urls'), 0);
    }
    public static function menu() { add_management_page('Qpedia Cleanup','Qpedia Cleanup','manage_options','qpedia-public-scope-cleanup',array(__CLASS__,'page')); }
    private static function state() { return wp_parse_args(get_option(self::OPTION,array()),array('deleted'=>array(),'mismatch'=>array(),'failed'=>array(),'completed'=>false)); }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $s=self::state(); $done=count($s['deleted']); $total=count(self::removals());
        echo '<div class="wrap"><h1>Qpedia Public Scope Cleanup</h1>';
        echo '<p><strong>حذف دائمی:</strong> ۱۱۸ مقاله؛ شامل ۶۳ مقاله تخصصی و ۵۵ مقاله ادغامی/تکراری.</p>';
        echo '<p>۸ مقاله بخش دانشمندان در فهرست حذف نیستند. برای ۵۵ URL ادغامی ریدایرکت ۳۰۱ و برای ۶۳ URL حذف‌شده پاسخ 410 برقرار می‌ماند.</p>';
        echo '<p>پیشرفت: '.esc_html($done).' از '.esc_html($total).'</p>';
        if (!empty($s['mismatch'])) echo '<div class="notice notice-error"><p>موارد ناسازگار حذف نشدند: '.esc_html(implode(', ',$s['mismatch'])).'</p></div>';
        if (!empty($s['failed'])) echo '<div class="notice notice-error"><p>حذف ناموفق: '.esc_html(implode(', ',$s['failed'])).'</p></div>';
        if ($s['completed']) { echo '<div class="notice notice-success"><p>عملیات کامل شده است. افزونه را برای حفظ ریدایرکت‌ها فعال نگه دارید.</p></div></div>'; return; }
        echo '<div class="notice notice-warning"><p>این عملیات غیرقابل بازگشت است. پیش از اجرا از پایگاه داده نسخه پشتیبان بگیرید.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field(self::NONCE); echo '<input type="hidden" name="action" value="qpedia_scope_cleanup">';
        echo '<p><label>برای تأیید عبارت <code>DELETE 118</code> را وارد کنید: <input name="confirm" required></label></p>';
        submit_button('حذف دائمی دسته بعدی (حداکثر ۲۰ مقاله)','delete'); echo '</form></div>';
    }
    public static function execute() {
        if (!current_user_can('manage_options')) wp_die('Forbidden',403);
        check_admin_referer(self::NONCE);
        if (trim((string)($_POST['confirm'] ?? '')) !== 'DELETE 118') wp_die('Confirmation phrase is incorrect.');
        $s=self::state(); $done=array_map('intval',$s['deleted']); $batch=0;
        foreach (self::removals() as $item) {
            if (in_array($item['id'],$done,true)) continue;
            if ($batch >= 20) break;
            $post=get_post($item['id']);
            if (!$post) { $s['deleted'][]=$item['id']; $batch++; continue; }
            if ($post->post_type !== 'quantum_article' || $post->post_name !== $item['slug']) { $s['mismatch'][]=$item['id'].'/'.$item['slug']; $batch++; continue; }
            $result=wp_delete_post($item['id'],true);
            if ($result) $s['deleted'][]=$item['id']; else $s['failed'][]=$item['id'];
            $batch++;
        }
        $s['deleted']=array_values(array_unique(array_map('intval',$s['deleted'])));
        $s['mismatch']=array_values(array_unique($s['mismatch'])); $s['failed']=array_values(array_unique($s['failed']));
        $s['completed']=(count($s['deleted'])===count(self::removals()) && !$s['mismatch'] && !$s['failed']);
        update_option(self::OPTION,$s,false);
        wp_safe_redirect(admin_url('tools.php?page=qpedia-public-scope-cleanup')); exit;
    }
    public static function route_removed_urls() {
        if (is_admin()) return;
        $path=trailingslashit('/'.trim((string)wp_parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH),'/'));
        $redirects=self::redirects();
        if (isset($redirects[$path])) { wp_safe_redirect($redirects[$path],301,'Qpedia Cleanup'); exit; }
        if (in_array($path,self::gone(),true)) { status_header(410); nocache_headers();
            wp_die('این مقاله از برنامه عمومی Qpedia حذف شده است.','محتوا حذف شده است',array('response'=>410)); }
    }
}
Qpedia_Public_Scope_Cleanup::boot();
