<?php
define('PORTAL_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// Public form - no login required
// requireLogin();

$page_title = 'Client Questionnaire';
$current_page = 'form';

// Check for draft/edit mode
$submission_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$draft_data = [];
if ($submission_id > 0) {
    // Load existing submission (public form - no user_id check)
    $stmt = $conn->prepare("SELECT form_data FROM questionnaire_submissions WHERE id = ?");
    $stmt->bind_param('i', $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $draft_data = json_decode($row['form_data'], true) ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container-fluid p-0">

<div class="container py-4">
    <div class="text-center mb-4">
        <h1 class="mb-3"><i class="bi bi-clipboard-check"></i> Static Website Client Intake Questionnaire</h1>
    </div>

    <div class="mb-3 text-end">
        <button class="btn btn-outline-primary" id="saveDraftBtn" type="button">
            <i class="bi bi-save me-1"></i> Save Draft
        </button>
    </div>

    <!-- Progress Indicator -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-primary progress-bar-striped" id="formProgress" role="progressbar"
                     style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                    Section 1 of 11
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php
            if ($_GET['success'] == 'submitted') {
                echo 'Questionnaire submitted successfully!';
            } elseif ($_GET['success'] == 'draft_saved') {
                echo 'Draft saved successfully!';
            }
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Main Form Card -->
    <div class="card shadow-sm">
        <div class="card-body">
            <form id="questionnaireForm" method="POST" action="form_handler.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="submission_id" value="<?php echo $submission_id; ?>">

                <!-- ==================== SECTION 1: BUSINESS CONTEXT ==================== -->
                <div class="questionnaire-section active" data-section="1">
                    <h3 class="section-heading">
                        <i class="bi bi-building"></i> Section 1: Business Context
                    </h3>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="business_name" class="form-label required">Business/Organization Name</label>
                            <input type="text" class="form-control" id="business_name" name="business_name"
                                   value="<?php echo htmlspecialchars($draft_data['business_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="contact_person" class="form-label required">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person"
                                   value="<?php echo htmlspecialchars($draft_data['contact_person'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="email" class="form-label required">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($draft_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($draft_data['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Industry & Background</h5>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="industry" class="form-label">Industry/Niche</label>
                            <select class="form-select" id="industry" name="industry">
                                <option value="">-- Select Industry --</option>
                                <option value="Restaurant">Restaurant</option>
                                <option value="Law Firm">Law Firm</option>
                                <option value="Construction">Construction</option>
                                <option value="Healthcare">Healthcare</option>
                                <option value="Retail">Retail</option>
                                <option value="Creative Agency">Creative Agency</option>
                                <option value="Real Estate">Real Estate</option>
                                <option value="Education">Education</option>
                                <option value="Non-profit">Non-profit</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="industry_other" class="form-label">If Other, please specify</label>
                            <input type="text" class="form-control" id="industry_other" name="industry_other"
                                   value="<?php echo htmlspecialchars($draft_data['industry_other'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="business_description" class="form-label">Business Description (2-3 sentences)</label>
                        <textarea class="form-control" id="business_description" name="business_description" rows="3"><?php echo htmlspecialchars($draft_data['business_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="years_in_business" class="form-label">Years in Business</label>
                            <input type="text" class="form-control" id="years_in_business" name="years_in_business"
                                   value="<?php echo htmlspecialchars($draft_data['years_in_business'] ?? ''); ?>">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="current_website" class="form-label">Current Website URL (if any)</label>
                            <input type="url" class="form-control" id="current_website" name="current_website"
                                   placeholder="https://" value="<?php echo htmlspecialchars($draft_data['current_website'] ?? ''); ?>">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Competitive Landscape</h5>
                    <p class="text-muted small">List 2-3 competitors or similar businesses (you can add more)</p>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="competitorsTable">
                            <thead>
                                <tr>
                                    <th style="width: 25%;">Competitor Name</th>
                                    <th style="width: 25%;">URL</th>
                                    <th style="width: 45%;">What they do well / What to avoid</th>
                                    <th style="width: 5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="competitorsTableBody">
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <tr data-index="<?php echo $i; ?>">
                                    <td><input type="text" class="form-control form-control-sm" name="competitors[<?php echo $i; ?>][name]" value="<?php echo htmlspecialchars($draft_data['competitors'][$i]['name'] ?? ''); ?>"></td>
                                    <td><input type="url" class="form-control form-control-sm" name="competitors[<?php echo $i; ?>][url]" placeholder="https://" value="<?php echo htmlspecialchars($draft_data['competitors'][$i]['url'] ?? ''); ?>"></td>
                                    <td><textarea class="form-control form-control-sm" name="competitors[<?php echo $i; ?>][notes]" rows="2"><?php echo htmlspecialchars($draft_data['competitors'][$i]['notes'] ?? ''); ?></textarea></td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-competitor-btn" title="Remove competitor">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mb-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addCompetitorBtn">
                            <i class="bi bi-plus-circle me-1"></i> Add Competitor
                        </button>
                    </div>
                </div>

                <!-- ==================== SECTION 2: PROJECT GOALS ==================== -->
                <div class="questionnaire-section" data-section="2">
                    <h3 class="section-heading">
                        <i class="bi bi-bullseye"></i> Section 2: Project Goals
                    </h3>

                    <h5 class="mb-3">Primary Purpose</h5>
                    <p class="text-muted small">Select the main purpose (choose ONE primary, mark others as secondary if applicable)</p>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Purpose</th>
                                    <th class="text-center" style="min-width: 80px;">Primary?</th>
                                    <th class="text-center" style="min-width: 100px;">Secondary?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $purposes = [
                                    'Brand/Online Presence',
                                    'Lead Generation',
                                    'Portfolio Showcase',
                                    'Information Hub',
                                    'Product/Service Catalog',
                                    'Event/Service Booking'
                                ];
                                foreach ($purposes as $purpose):
                                ?>
                                <tr>
                                    <td><?php echo $purpose; ?></td>
                                    <td class="text-center">
                                        <input type="radio" name="primary_purpose" value="<?php echo $purpose; ?>" class="form-check-input">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" name="secondary_purpose[]" value="<?php echo $purpose; ?>" class="form-check-input">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td><input type="text" class="form-control form-control-sm" name="primary_purpose_other" placeholder="Other (specify)"></td>
                                    <td class="text-center">
                                        <input type="radio" name="primary_purpose" value="Other" class="form-check-input">
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" name="secondary_purpose[]" value="Other" class="form-check-input">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Success Metrics</h5>

                    <div class="mb-3">
                        <label for="success_definition" class="form-label required">What does success look like for this website?</label>
                        <textarea class="form-control" id="success_definition" name="success_definition" rows="3" required><?php echo htmlspecialchars($draft_data['success_definition'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="key_problems" class="form-label">Key problems this website should solve</label>
                        <textarea class="form-control" id="key_problems" name="key_problems" rows="3"><?php echo htmlspecialchars($draft_data['key_problems'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="primary_cta" class="form-label required">Primary Call-to-Action (the ONE thing visitors should do)</label>
                            <select class="form-select" id="primary_cta" name="primary_cta" required>
                                <option value="">-- Select Primary CTA --</option>
                                <option value="Call us">Call us</option>
                                <option value="Fill contact form">Fill contact form</option>
                                <option value="Request quote">Request quote</option>
                                <option value="Book appointment">Book appointment</option>
                                <option value="View portfolio">View portfolio</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="primary_cta_other" class="form-label">If Other CTA, please specify</label>
                            <input type="text" class="form-control" id="primary_cta_other" name="primary_cta_other"
                                   value="<?php echo htmlspecialchars($draft_data['primary_cta_other'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- ==================== SECTION 3: TARGET AUDIENCE ==================== -->
                <div class="questionnaire-section" data-section="3">
                    <h3 class="section-heading">
                        <i class="bi bi-people"></i> Section 3: Target Audience
                    </h3>

                    <div class="mb-3">
                        <label for="primary_audience" class="form-label">Who is the primary audience? (describe in 1-2 sentences)</label>
                        <textarea class="form-control" id="primary_audience" name="primary_audience" rows="2"><?php echo htmlspecialchars($draft_data['primary_audience'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Geographic Focus</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="geographic_focus[]" value="Local" id="geo_local">
                                <label class="form-check-label" for="geo_local">Local</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="geographic_focus[]" value="Regional" id="geo_regional">
                                <label class="form-check-label" for="geo_regional">Regional</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="geographic_focus[]" value="National" id="geo_national">
                                <label class="form-check-label" for="geo_national">National</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="geographic_focus[]" value="International" id="geo_intl">
                                <label class="form-check-label" for="geo_intl">International</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="geographic_location" class="form-label">City/Area (if Local or Regional)</label>
                            <input type="text" class="form-control" id="geographic_location" name="geographic_location"
                                   value="<?php echo htmlspecialchars($draft_data['geographic_location'] ?? ''); ?>">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="age_range" class="form-label">Age Range (if relevant)</label>
                            <input type="text" class="form-control" id="age_range" name="age_range"
                                   value="<?php echo htmlspecialchars($draft_data['age_range'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="visitor_intent" class="form-label">What are visitors looking for when they arrive?</label>
                        <textarea class="form-control" id="visitor_intent" name="visitor_intent" rows="2"><?php echo htmlspecialchars($draft_data['visitor_intent'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">How do visitors typically find businesses like this?</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visitor_sources[]" value="Google Search" id="source_google">
                                <label class="form-check-label" for="source_google">Google Search</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visitor_sources[]" value="Social Media" id="source_social">
                                <label class="form-check-label" for="source_social">Social Media</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visitor_sources[]" value="Word of mouth" id="source_word">
                                <label class="form-check-label" for="source_word">Word of mouth</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visitor_sources[]" value="Ads" id="source_ads">
                                <label class="form-check-label" for="source_ads">Ads</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visitor_sources[]" value="Other" id="source_other">
                                <label class="form-check-label" for="source_other">Other</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="visitor_sources_other" class="form-label">If Other source, please specify</label>
                        <input type="text" class="form-control" id="visitor_sources_other" name="visitor_sources_other"
                               value="<?php echo htmlspecialchars($draft_data['visitor_sources_other'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Section navigation will continue for sections 4-11 -->
                <!-- Due to length constraints, I'll create a condensed version with all sections -->

                <!-- ==================== SECTION 4: PAGES & CONTENT STRUCTURE ==================== -->
                <div class="questionnaire-section" data-section="4">
                    <h3 class="section-heading">
                        <i class="bi bi-file-text"></i> Section 4: Pages & Content Structure
                    </h3>

                    <h5 class="mb-3">Standard Page Set</h5>
                    <p class="text-muted small">Mark Yes/No for each page. Add notes for special requirements.</p>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th class="text-center" style="min-width: 80px;">Include?</th>
                                    <th class="text-center" style="min-width: 100px;">Priority (1-10)</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $pages = [
                                    'home' => ['name' => 'Home', 'placeholder' => ''],
                                    'about' => ['name' => 'About', 'placeholder' => ''],
                                    'services' => ['name' => 'Services/Products', 'placeholder' => 'How many services/products?'],
                                    'portfolio' => ['name' => 'Portfolio/Gallery/Projects', 'placeholder' => 'Approx. number of items?'],
                                    'contact' => ['name' => 'Contact', 'placeholder' => ''],
                                    'faq' => ['name' => 'FAQ', 'placeholder' => 'Approx. number of questions?'],
                                    'blog' => ['name' => 'Blog/News', 'placeholder' => 'Static articles or client-updated?'],
                                    'testimonials' => ['name' => 'Testimonials/Reviews', 'placeholder' => 'Approx. number?'],
                                    'privacy' => ['name' => 'Privacy Policy', 'placeholder' => ''],
                                    'terms' => ['name' => 'Terms of Service', 'placeholder' => '']
                                ];
                                foreach ($pages as $key => $page):
                                ?>
                                <tr>
                                    <td><strong><?php echo $page['name']; ?></strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="pages[<?php echo $key; ?>][include]" value="yes" id="page_<?php echo $key; ?>_yes">
                                            <label class="form-check-label" for="page_<?php echo $key; ?>_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="pages[<?php echo $key; ?>][include]" value="no" id="page_<?php echo $key; ?>_no">
                                            <label class="form-check-label" for="page_<?php echo $key; ?>_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="number" class="form-control form-control-sm" name="pages[<?php echo $key; ?>][priority]" min="1" max="10"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="pages[<?php echo $key; ?>][notes]" placeholder="<?php echo $page['placeholder']; ?>"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Additional Pages</h5>
                    <p class="text-muted small">List any pages not covered above</p>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Page Name</th>
                                    <th>Purpose</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <tr>
                                    <td><input type="text" class="form-control form-control-sm" name="additional_pages[<?php echo $i; ?>][name]"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="additional_pages[<?php echo $i; ?>][purpose]"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="additional_pages[<?php echo $i; ?>][notes]"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Total Page Count</h5>
                    <div class="row">
                        <div class="col-12 col-md-4 mb-3">
                            <label for="standard_pages_count" class="form-label">Standard pages (from above)</label>
                            <input type="number" class="form-control" id="standard_pages_count" name="page_count[standard]" min="0" readonly>
                        </div>
                        <div class="col-12 col-md-4 mb-3">
                            <label for="additional_pages_count" class="form-label">Additional pages</label>
                            <input type="number" class="form-control" id="additional_pages_count" name="page_count[additional]" min="0" readonly>
                        </div>
                        <div class="col-12 col-md-4 mb-3">
                            <label for="total_pages_count" class="form-label"><strong>TOTAL</strong></label>
                            <input type="number" class="form-control fw-bold" id="total_pages_count" name="page_count[total]" min="0" readonly>
                        </div>
                    </div>
                </div>

                <!-- ==================== SECTION 5: DESIGN PREFERENCES ==================== -->
                <div class="questionnaire-section" data-section="5">
                    <h3 class="section-heading">
                        <i class="bi bi-palette"></i> Section 5: Design Preferences
                    </h3>

                    <h5 class="mb-3">Reference Websites</h5>
                    <p class="text-muted small">Provide 2-3 websites you like (doesn't need to be in your industry, you can add more)</p>

                    <div class="table-responsive mb-2">
                        <table class="table table-bordered" id="referenceSitesTable">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Website URL</th>
                                    <th style="width: 60%;">What you like about it</th>
                                    <th style="width: 5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="referenceSitesTableBody">
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <tr data-index="<?php echo $i; ?>">
                                    <td><input type="url" class="form-control form-control-sm" name="reference_sites[<?php echo $i; ?>][url]" placeholder="https://" value="<?php echo htmlspecialchars($draft_data['reference_sites'][$i]['url'] ?? ''); ?>"></td>
                                    <td><textarea class="form-control form-control-sm" name="reference_sites[<?php echo $i; ?>][likes]" rows="2"><?php echo htmlspecialchars($draft_data['reference_sites'][$i]['likes'] ?? ''); ?></textarea></td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-reference-site-btn" title="Remove website">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mb-4">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addReferenceSiteBtn">
                            <i class="bi bi-plus-circle me-1"></i> Add Reference Website
                        </button>
                    </div>

                    <h6>Websites to avoid (optional)</h6>
                    <div class="table-responsive mb-2">
                        <table class="table table-bordered" id="avoidSitesTable">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Website URL</th>
                                    <th style="width: 60%;">What you dislike</th>
                                    <th style="width: 5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="avoidSitesTableBody">
                                <tr data-index="0">
                                    <td><input type="url" class="form-control form-control-sm" name="avoid_sites[0][url]" placeholder="https://" value="<?php echo htmlspecialchars($draft_data['avoid_sites'][0]['url'] ?? ''); ?>"></td>
                                    <td><textarea class="form-control form-control-sm" name="avoid_sites[0][dislikes]" rows="2"><?php echo htmlspecialchars($draft_data['avoid_sites'][0]['dislikes'] ?? ''); ?></textarea></td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-avoid-site-btn" title="Remove website">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mb-4">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addAvoidSiteBtn">
                            <i class="bi bi-plus-circle me-1"></i> Add Website to Avoid
                        </button>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Brand Assets</h5>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Logo</label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[logo][available]" value="yes" id="logo_yes">
                                    <label class="form-check-label" for="logo_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[logo][available]" value="no" id="logo_no">
                                    <label class="form-check-label" for="logo_no">No</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[logo][available]" value="need" id="logo_need">
                                    <label class="form-check-label" for="logo_need">Need one</label>
                                </div>
                            </div>
                            <div>
                                <small>Format:</small>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="brand_assets[logo][format][]" value="PNG" id="logo_png">
                                        <label class="form-check-label" for="logo_png">PNG</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="brand_assets[logo][format][]" value="SVG" id="logo_svg">
                                        <label class="form-check-label" for="logo_svg">SVG</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="brand_assets[logo][format][]" value="AI" id="logo_ai">
                                        <label class="form-check-label" for="logo_ai">AI</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand Colors</label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[colors][available]" value="yes" id="colors_yes">
                                    <label class="form-check-label" for="colors_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[colors][available]" value="no" id="colors_no">
                                    <label class="form-check-label" for="colors_no">No</label>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm" name="brand_assets[colors][details]" placeholder="Hex codes or describe colors">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Brand Fonts</label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[fonts][available]" value="yes" id="fonts_yes">
                                    <label class="form-check-label" for="fonts_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[fonts][available]" value="no" id="fonts_no">
                                    <label class="form-check-label" for="fonts_no">No</label>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm" name="brand_assets[fonts][details]" placeholder="Font names">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand Guidelines Document</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[guidelines][available]" value="yes" id="guidelines_yes">
                                    <label class="form-check-label" for="guidelines_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[guidelines][available]" value="no" id="guidelines_no">
                                    <label class="form-check-label" for="guidelines_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Photography/Images</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[photography][available]" value="yes" id="photo_yes">
                                    <label class="form-check-label" for="photo_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[photography][available]" value="partial" id="photo_partial">
                                    <label class="form-check-label" for="photo_partial">Partial</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="brand_assets[photography][available]" value="no" id="photo_no">
                                    <label class="form-check-label" for="photo_no">No</label>
                                </div>
                            </div>
                            <small class="text-muted">See Section 8 for details</small>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Style Preferences (If No References)</h5>
                    <p class="text-muted small">Only fill this if reference websites section is empty</p>

                    <div class="mb-3">
                        <label class="form-label">Overall Feel</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[feel][]" value="Modern" id="feel_modern">
                                <label class="form-check-label" for="feel_modern">Modern</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[feel][]" value="Classic" id="feel_classic">
                                <label class="form-check-label" for="feel_classic">Classic</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[feel][]" value="Minimal" id="feel_minimal">
                                <label class="form-check-label" for="feel_minimal">Minimal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[feel][]" value="Bold" id="feel_bold">
                                <label class="form-check-label" for="feel_bold">Bold</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[feel][]" value="Professional" id="feel_prof">
                                <label class="form-check-label" for="feel_prof">Professional</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[feel][]" value="Playful" id="feel_playful">
                                <label class="form-check-label" for="feel_playful">Playful</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Color Mood</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[color_mood][]" value="Light/Bright" id="mood_light">
                                <label class="form-check-label" for="mood_light">Light/Bright</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[color_mood][]" value="Dark" id="mood_dark">
                                <label class="form-check-label" for="mood_dark">Dark</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[color_mood][]" value="Colorful" id="mood_colorful">
                                <label class="form-check-label" for="mood_colorful">Colorful</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="style_preferences[color_mood][]" value="Neutral/Muted" id="mood_neutral">
                                <label class="form-check-label" for="mood_neutral">Neutral/Muted</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Layout</label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="style_preferences[layout]" value="Clean with lots of whitespace" id="layout_clean">
                                <label class="form-check-label" for="layout_clean">Clean with lots of whitespace</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="style_preferences[layout]" value="Dense with information" id="layout_dense">
                                <label class="form-check-label" for="layout_dense">Dense with information</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="industry_expectations" class="form-label">Industry Expectations</label>
                        <textarea class="form-control" id="industry_expectations" name="style_preferences[industry_expectations]" rows="2" placeholder="What do competitors' sites look like? What do customers expect?"></textarea>
                    </div>
                </div>

                <!-- ==================== SECTION 6: FEATURES & FUNCTIONALITY ==================== -->
                <div class="questionnaire-section" data-section="6">
                    <h3 class="section-heading">
                        <i class="bi bi-gear"></i> Section 6: Features & Functionality
                    </h3>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <th style="width: 100px;">Include?</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Contact Form</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[contact_form][include]" value="yes" id="cf_yes">
                                            <label class="form-check-label" for="cf_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[contact_form][include]" value="no" id="cf_no">
                                            <label class="form-check-label" for="cf_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Fields needed:</small>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[contact_form][fields][]" value="Name" id="cf_name">
                                                <label class="form-check-label" for="cf_name">Name</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[contact_form][fields][]" value="Email" id="cf_email">
                                                <label class="form-check-label" for="cf_email">Email</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[contact_form][fields][]" value="Phone" id="cf_phone">
                                                <label class="form-check-label" for="cf_phone">Phone</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[contact_form][fields][]" value="Message" id="cf_message">
                                                <label class="form-check-label" for="cf_message">Message</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Social Media Links</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[social_media][include]" value="yes" id="sm_yes">
                                            <label class="form-check-label" for="sm_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[social_media][include]" value="no" id="sm_no">
                                            <label class="form-check-label" for="sm_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Platforms:</small>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[social_media][platforms][]" value="Facebook" id="sm_fb">
                                                <label class="form-check-label" for="sm_fb">Facebook</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[social_media][platforms][]" value="Instagram" id="sm_ig">
                                                <label class="form-check-label" for="sm_ig">Instagram</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[social_media][platforms][]" value="LinkedIn" id="sm_li">
                                                <label class="form-check-label" for="sm_li">LinkedIn</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Newsletter Signup</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[newsletter][include]" value="yes" id="news_yes">
                                            <label class="form-check-label" for="news_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[newsletter][include]" value="no" id="news_no">
                                            <label class="form-check-label" for="news_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Email service:</small>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="features[newsletter][service]" value="Mailchimp" id="news_mc">
                                                <label class="form-check-label" for="news_mc">Mailchimp</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="features[newsletter][service]" value="None yet" id="news_none">
                                                <label class="form-check-label" for="news_none">None yet</label>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="features[newsletter][other_service]" placeholder="Other service name">
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Google Maps Embed</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[google_maps][include]" value="yes" id="gm_yes">
                                            <label class="form-check-label" for="gm_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[google_maps][include]" value="no" id="gm_no">
                                            <label class="form-check-label" for="gm_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="features[google_maps][address]" placeholder="Address"></td>
                                </tr>
                                <tr>
                                    <td><strong>Image Gallery/Lightbox</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[gallery][include]" value="yes" id="gallery_yes">
                                            <label class="form-check-label" for="gallery_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[gallery][include]" value="no" id="gallery_no">
                                            <label class="form-check-label" for="gallery_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Video Embeds</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[video][include]" value="yes" id="video_yes">
                                            <label class="form-check-label" for="video_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[video][include]" value="no" id="video_no">
                                            <label class="form-check-label" for="video_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Source:</small>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[video][source][]" value="YouTube" id="vid_yt">
                                                <label class="form-check-label" for="vid_yt">YouTube</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[video][source][]" value="Vimeo" id="vid_vimeo">
                                                <label class="form-check-label" for="vid_vimeo">Vimeo</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="features[video][source][]" value="Self-hosted" id="vid_self">
                                                <label class="form-check-label" for="vid_self">Self-hosted</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Testimonial Slider</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[testimonial_slider][include]" value="yes" id="testi_yes">
                                            <label class="form-check-label" for="testi_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[testimonial_slider][include]" value="no" id="testi_no">
                                            <label class="form-check-label" for="testi_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>FAQ Accordion</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[faq_accordion][include]" value="yes" id="faq_yes">
                                            <label class="form-check-label" for="faq_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[faq_accordion][include]" value="no" id="faq_no">
                                            <label class="form-check-label" for="faq_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Site Search</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[site_search][include]" value="yes" id="search_yes">
                                            <label class="form-check-label" for="search_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[site_search][include]" value="no" id="search_no">
                                            <label class="form-check-label" for="search_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Multi-language</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[multi_language][include]" value="yes" id="lang_yes">
                                            <label class="form-check-label" for="lang_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[multi_language][include]" value="no" id="lang_no">
                                            <label class="form-check-label" for="lang_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="features[multi_language][languages]" placeholder="Languages"></td>
                                </tr>
                                <tr>
                                    <td><strong>Live Chat Widget</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[live_chat][include]" value="yes" id="chat_yes">
                                            <label class="form-check-label" for="chat_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[live_chat][include]" value="no" id="chat_no">
                                            <label class="form-check-label" for="chat_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="features[live_chat][service]" placeholder="Service"></td>
                                </tr>
                                <tr>
                                    <td><strong>Booking/Scheduling</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[booking][include]" value="yes" id="book_yes">
                                            <label class="form-check-label" for="book_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="features[booking][include]" value="no" id="book_no">
                                            <label class="form-check-label" for="book_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Service:</small>
                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="features[booking][service]" value="Calendly" id="book_calendly">
                                                <label class="form-check-label" for="book_calendly">Calendly</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="features[booking][service]" value="Acuity" id="book_acuity">
                                                <label class="form-check-label" for="book_acuity">Acuity</label>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control form-control-sm mt-1" name="features[booking][other_service]" placeholder="Other service name">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Content Management</h5>

                    <div class="mb-3">
                        <label class="form-label">Does client need to update content themselves?</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cms[client_updates]" value="yes" id="cms_yes">
                                <label class="form-check-label" for="cms_yes">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cms[client_updates]" value="no" id="cms_no">
                                <label class="form-check-label" for="cms_no">No</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">If yes, what content?</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cms[content_types][]" value="Blog posts" id="cms_blog">
                                <label class="form-check-label" for="cms_blog">Blog posts</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cms[content_types][]" value="Portfolio items" id="cms_portfolio">
                                <label class="form-check-label" for="cms_portfolio">Portfolio items</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cms[content_types][]" value="Team members" id="cms_team">
                                <label class="form-check-label" for="cms_team">Team members</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cms[content_types][]" value="Testimonials" id="cms_testimonials">
                                <label class="form-check-label" for="cms_testimonials">Testimonials</label>
                            </div>
                        </div>
                        <input type="text" class="form-control mt-2" name="cms[other_content]" placeholder="Other (specify)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CMS Solution</label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cms[solution]" value="Simple PHP admin panel" id="cms_php">
                                <label class="form-check-label" for="cms_php">Simple PHP admin panel</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cms[solution]" value="WordPress" id="cms_wp">
                                <label class="form-check-label" for="cms_wp">WordPress (only if explicitly needed)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="cms[solution]" value="None" id="cms_none">
                                <label class="form-check-label" for="cms_none">None</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Form Handling</h5>

                    <div class="mb-3">
                        <label class="form-label">Contact form handling</label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="form_handling[method]" value="PHP mail()" id="fh_php">
                                <label class="form-check-label" for="fh_php">PHP mail()</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="form_handling[method]" value="SMTP service" id="fh_smtp">
                                <label class="form-check-label" for="fh_smtp">SMTP service (Mailgun, SendGrid, etc.)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="form_handling[method]" value="Third-party" id="fh_third">
                                <label class="form-check-label" for="fh_third">Third-party (Formspree, Netlify Forms)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Form submissions storage</label>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="form_handling[storage]" value="Email only" id="fs_email">
                                <label class="form-check-label" for="fs_email">Email only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="form_handling[storage]" value="Database + Email" id="fs_db">
                                <label class="form-check-label" for="fs_db">Database + Email</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="form_handling[storage]" value="Third-party service" id="fs_third">
                                <label class="form-check-label" for="fs_third">Third-party service</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CAPTCHA/Spam protection</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="form_handling[captcha]" value="yes" id="captcha_yes">
                                <label class="form-check-label" for="captcha_yes">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="form_handling[captcha]" value="no" id="captcha_no">
                                <label class="form-check-label" for="captcha_no">No</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== SECTION 7: TECHNICAL REQUIREMENTS ==================== -->
                <div class="questionnaire-section" data-section="7">
                    <h3 class="section-heading">
                        <i class="bi bi-server"></i> Section 7: Technical Requirements
                    </h3>

                    <h5 class="mb-3">Domain</h5>
                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Domain owned?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="domain[owned]" value="yes" id="dom_yes">
                                    <label class="form-check-label" for="dom_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="domain[owned]" value="no" id="dom_no">
                                    <label class="form-check-label" for="dom_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="domain_name" class="form-label">Domain name (if owned)</label>
                            <input type="text" class="form-control" id="domain_name" name="domain[name]" placeholder="example.com">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Hosting</h5>
                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Hosting account exists?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[exists]" value="yes" id="host_yes">
                                    <label class="form-check-label" for="host_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[exists]" value="no" id="host_no">
                                    <label class="form-check-label" for="host_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="hosting_provider" class="form-label">Hosting provider</label>
                            <input type="text" class="form-control" id="hosting_provider" name="hosting[provider]">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">cPanel access available?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[cpanel]" value="yes" id="cpanel_yes">
                                    <label class="form-check-label" for="cpanel_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[cpanel]" value="no" id="cpanel_no">
                                    <label class="form-check-label" for="cpanel_no">No</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[cpanel]" value="unknown" id="cpanel_unknown">
                                    <label class="form-check-label" for="cpanel_unknown">Unknown</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">SSL certificate</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[ssl]" value="have" id="ssl_have">
                                    <label class="form-check-label" for="ssl_have">Have one</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[ssl]" value="need" id="ssl_need">
                                    <label class="form-check-label" for="ssl_need">Need one</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="hosting[ssl]" value="letsencrypt" id="ssl_le">
                                    <label class="form-check-label" for="ssl_le">Let's Encrypt</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Email Setup</h5>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Professional email needed? (user@domain.com)</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="email_setup[needed]" value="yes" id="email_yes">
                                    <label class="form-check-label" for="email_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="email_setup[needed]" value="no" id="email_no">
                                    <label class="form-check-label" for="email_no">No</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="email_setup[needed]" value="already_have" id="email_have">
                                    <label class="form-check-label" for="email_have">Already have</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="email_accounts" class="form-label">Number of email accounts</label>
                            <input type="number" class="form-control" id="email_accounts" name="email_setup[accounts]" min="0">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="email_forwarding" class="form-label">Email forwarding requirements</label>
                            <input type="text" class="form-control" id="email_forwarding" name="email_setup[forwarding]">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Webmail preference</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="email_setup[webmail]" value="Roundcube" id="webmail_rc">
                                    <label class="form-check-label" for="webmail_rc">Roundcube</label>
                                </div>
                            </div>
                            <input type="text" class="form-control mt-1" name="email_setup[webmail_other]" placeholder="Other (specify)">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Integrations & Analytics</h5>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Integration</th>
                                    <th style="width: 100px;">Include?</th>
                                    <th style="width: 120px;">Account exists?</th>
                                    <th>ID/Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Google Analytics</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_analytics][include]" value="yes" id="ga_yes">
                                            <label class="form-check-label" for="ga_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_analytics][include]" value="no" id="ga_no">
                                            <label class="form-check-label" for="ga_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_analytics][account_exists]" value="yes" id="ga_acc_yes">
                                            <label class="form-check-label" for="ga_acc_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_analytics][account_exists]" value="no" id="ga_acc_no">
                                            <label class="form-check-label" for="ga_acc_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="integrations[google_analytics][id]" placeholder="ID"></td>
                                </tr>
                                <tr>
                                    <td><strong>Google Tag Manager</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_tag_manager][include]" value="yes" id="gtm_yes">
                                            <label class="form-check-label" for="gtm_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_tag_manager][include]" value="no" id="gtm_no">
                                            <label class="form-check-label" for="gtm_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_tag_manager][account_exists]" value="yes" id="gtm_acc_yes">
                                            <label class="form-check-label" for="gtm_acc_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_tag_manager][account_exists]" value="no" id="gtm_acc_no">
                                            <label class="form-check-label" for="gtm_acc_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="integrations[google_tag_manager][id]" placeholder="ID"></td>
                                </tr>
                                <tr>
                                    <td><strong>Google Search Console</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_search_console][include]" value="yes" id="gsc_yes">
                                            <label class="form-check-label" for="gsc_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_search_console][include]" value="no" id="gsc_no">
                                            <label class="form-check-label" for="gsc_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_search_console][account_exists]" value="yes" id="gsc_acc_yes">
                                            <label class="form-check-label" for="gsc_acc_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[google_search_console][account_exists]" value="no" id="gsc_acc_no">
                                            <label class="form-check-label" for="gsc_acc_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Facebook Pixel</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[facebook_pixel][include]" value="yes" id="fbp_yes">
                                            <label class="form-check-label" for="fbp_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[facebook_pixel][include]" value="no" id="fbp_no">
                                            <label class="form-check-label" for="fbp_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[facebook_pixel][account_exists]" value="yes" id="fbp_acc_yes">
                                            <label class="form-check-label" for="fbp_acc_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[facebook_pixel][account_exists]" value="no" id="fbp_acc_no">
                                            <label class="form-check-label" for="fbp_acc_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="integrations[facebook_pixel][id]" placeholder="ID"></td>
                                </tr>
                                <tr>
                                    <td><strong>Other tracking</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[other_tracking][include]" value="yes" id="ot_yes">
                                            <label class="form-check-label" for="ot_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="integrations[other_tracking][include]" value="no" id="ot_no">
                                            <label class="form-check-label" for="ot_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                    <td><input type="text" class="form-control form-control-sm" name="integrations[other_tracking][details]" placeholder="Specify"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Third-Party Services</h5>
                    <p class="text-muted small">List any external services that need integration</p>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Service Type</th>
                                    <th>Service Name</th>
                                    <th>Integration Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Booking/Scheduling</td>
                                    <td><input type="text" class="form-control form-control-sm" name="third_party[booking][name]"></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="third_party[booking][type]" value="Embed" id="tp_book_embed">
                                                <label class="form-check-label" for="tp_book_embed">Embed</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="third_party[booking][type]" value="Link" id="tp_book_link">
                                                <label class="form-check-label" for="tp_book_link">Link</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="third_party[booking][type]" value="API" id="tp_book_api">
                                                <label class="form-check-label" for="tp_book_api">API</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Payment</td>
                                    <td><input type="text" class="form-control form-control-sm" name="third_party[payment][name]"></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="third_party[payment][type]" value="Embed" id="tp_pay_embed">
                                                <label class="form-check-label" for="tp_pay_embed">Embed</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="third_party[payment][type]" value="Link" id="tp_pay_link">
                                                <label class="form-check-label" for="tp_pay_link">Link</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="third_party[payment][type]" value="API" id="tp_pay_api">
                                                <label class="form-check-label" for="tp_pay_api">API</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>CRM</td>
                                    <td><input type="text" class="form-control form-control-sm" name="third_party[crm][name]"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="third_party[crm][type]" placeholder="Integration type"></td>
                                </tr>
                                <tr>
                                    <td>Other</td>
                                    <td><input type="text" class="form-control form-control-sm" name="third_party[other][name]"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="third_party[other][type]" placeholder="Integration type"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== SECTION 8: CONTENT & ASSETS ==================== -->
                <div class="questionnaire-section" data-section="8">
                    <h3 class="section-heading">
                        <i class="bi bi-images"></i> Section 8: Content & Assets
                    </h3>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Who writes the copy?</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="content[writer]" value="Client provides" id="writer_client">
                                    <label class="form-check-label" for="writer_client">Client provides</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="content[writer]" value="Developer writes" id="writer_dev">
                                    <label class="form-check-label" for="writer_dev">Developer writes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="content[writer]" value="Collaborate" id="writer_collab">
                                    <label class="form-check-label" for="writer_collab">Collaborate</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Copy status</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="content[status]" value="Ready" id="content_ready">
                                    <label class="form-check-label" for="content_ready">Ready</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="content[status]" value="Partial" id="content_partial">
                                    <label class="form-check-label" for="content_partial">Partial</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="content[status]" value="Not started" id="content_not">
                                    <label class="form-check-label" for="content_not">Not started</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="content_percent" class="form-label">If partial, percentage complete</label>
                            <input type="text" class="form-control" id="content_percent" name="content[percent_complete]" placeholder="e.g., 50%">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="content_deadline" class="form-label">Content deadline</label>
                            <input type="text" class="form-control" id="content_deadline" name="content[deadline]">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Existing content to migrate?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="content[migrate]" value="yes" id="migrate_yes">
                                    <label class="form-check-label" for="migrate_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="content[migrate]" value="no" id="migrate_no">
                                    <label class="form-check-label" for="migrate_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="migrate_source" class="form-label">Migration source</label>
                            <input type="text" class="form-control" id="migrate_source" name="content[migrate_source]">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Images</h5>
                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Client provides images?</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="images[client_provides]" value="yes" id="img_yes">
                                    <label class="form-check-label" for="img_yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="images[client_provides]" value="partial" id="img_partial">
                                    <label class="form-check-label" for="img_partial">Partial</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="images[client_provides]" value="no" id="img_no">
                                    <label class="form-check-label" for="img_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Stock photos needed?</label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="images[stock_needed]" value="yes" id="stock_yes">
                                    <label class="form-check-label" for="stock_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="images[stock_needed]" value="no" id="stock_no">
                                    <label class="form-check-label" for="stock_no">No</label>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm" name="images[stock_budget]" placeholder="Budget">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Professional photography needed?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="images[professional_needed]" value="yes" id="prof_photo_yes">
                                    <label class="form-check-label" for="prof_photo_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="images[professional_needed]" value="no" id="prof_photo_no">
                                    <label class="form-check-label" for="prof_photo_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Image optimization needed?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="images[optimization]" value="yes" id="img_opt_yes">
                                    <label class="form-check-label" for="img_opt_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="images[optimization]" value="no" id="img_opt_no">
                                    <label class="form-check-label" for="img_opt_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image formats available</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="images[formats][]" value="JPG" id="fmt_jpg">
                                <label class="form-check-label" for="fmt_jpg">JPG</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="images[formats][]" value="PNG" id="fmt_png">
                                <label class="form-check-label" for="fmt_png">PNG</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="images[formats][]" value="RAW" id="fmt_raw">
                                <label class="form-check-label" for="fmt_raw">RAW</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="images[formats][]" value="Other" id="fmt_other">
                                <label class="form-check-label" for="fmt_other">Other</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Other Assets</h5>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Asset Type</th>
                                    <th style="width: 150px;">Available?</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Videos</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="other_assets[videos][available]" value="yes" id="asset_vid_yes">
                                            <label class="form-check-label" for="asset_vid_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="other_assets[videos][available]" value="no" id="asset_vid_no">
                                            <label class="form-check-label" for="asset_vid_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="other_assets[videos][notes]"></td>
                                </tr>
                                <tr>
                                    <td><strong>PDFs/Documents</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="other_assets[pdfs][available]" value="yes" id="asset_pdf_yes">
                                            <label class="form-check-label" for="asset_pdf_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="other_assets[pdfs][available]" value="no" id="asset_pdf_no">
                                            <label class="form-check-label" for="asset_pdf_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="other_assets[pdfs][notes]"></td>
                                </tr>
                                <tr>
                                    <td><strong>Icons</strong></td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="other_assets[icons][available]" value="yes" id="asset_icon_yes">
                                            <label class="form-check-label" for="asset_icon_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="other_assets[icons][available]" value="no" id="asset_icon_no">
                                            <label class="form-check-label" for="asset_icon_no">No</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="other_assets[icons][available]" value="icon_library" id="asset_icon_lib">
                                            <label class="form-check-label" for="asset_icon_lib">Use icon library</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="other_assets[icons][notes]"></td>
                                </tr>
                                <tr>
                                    <td><strong>Illustrations</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="other_assets[illustrations][available]" value="yes" id="asset_illust_yes">
                                            <label class="form-check-label" for="asset_illust_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="other_assets[illustrations][available]" value="no" id="asset_illust_no">
                                            <label class="form-check-label" for="asset_illust_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="other_assets[illustrations][notes]"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== SECTION 9: SEO & LEGAL ==================== -->
                <div class="questionnaire-section" data-section="9">
                    <h3 class="section-heading">
                        <i class="bi bi-search"></i> Section 9: SEO & Legal
                    </h3>

                    <div class="mb-3">
                        <label for="target_keywords" class="form-label">Target keywords (if known)</label>
                        <textarea class="form-control" id="target_keywords" name="seo[keywords]" rows="2"></textarea>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Local SEO focus?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[local_focus]" value="yes" id="seo_local_yes">
                                    <label class="form-check-label" for="seo_local_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[local_focus]" value="no" id="seo_local_no">
                                    <label class="form-check-label" for="seo_local_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="seo_location" class="form-label">Location for Local SEO</label>
                            <input type="text" class="form-control" id="seo_location" name="seo[location]">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Google Business Profile</label>
                            <div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="seo[google_business]" value="have" id="gbp_have">
                                    <label class="form-check-label" for="gbp_have">Have one</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="seo[google_business]" value="need_setup" id="gbp_need">
                                    <label class="form-check-label" for="gbp_need">Need setup</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="seo[google_business]" value="not_needed" id="gbp_not">
                                    <label class="form-check-label" for="gbp_not">Not needed</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Sitemap required?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[sitemap]" value="yes" id="sitemap_yes">
                                    <label class="form-check-label" for="sitemap_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[sitemap]" value="no" id="sitemap_no">
                                    <label class="form-check-label" for="sitemap_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">robots.txt required?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[robots_txt]" value="yes" id="robots_yes">
                                    <label class="form-check-label" for="robots_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[robots_txt]" value="no" id="robots_no">
                                    <label class="form-check-label" for="robots_no">No</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Schema markup</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[schema][include]" value="yes" id="schema_yes">
                                    <label class="form-check-label" for="schema_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="seo[schema][include]" value="no" id="schema_no">
                                    <label class="form-check-label" for="schema_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Schema types</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="seo[schema][types][]" value="LocalBusiness" id="schema_local">
                                <label class="form-check-label" for="schema_local">LocalBusiness</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="seo[schema][types][]" value="Organization" id="schema_org">
                                <label class="form-check-label" for="schema_org">Organization</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="seo[schema][types][]" value="Product" id="schema_product">
                                <label class="form-check-label" for="schema_product">Product</label>
                            </div>
                        </div>
                        <input type="text" class="form-control mt-2" name="seo[schema][other]" placeholder="Other (specify)">
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Legal & Compliance</h5>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Requirement</th>
                                    <th style="width: 150px;">Needed?</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Privacy Policy</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[privacy_policy][needed]" value="yes" id="privacy_yes">
                                            <label class="form-check-label" for="privacy_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[privacy_policy][needed]" value="no" id="privacy_no">
                                            <label class="form-check-label" for="privacy_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[privacy_policy][source]" value="client" id="privacy_client">
                                            <label class="form-check-label" for="privacy_client">Client provides</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[privacy_policy][source]" value="generate" id="privacy_gen">
                                            <label class="form-check-label" for="privacy_gen">Generate template</label>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Terms of Service</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[terms_of_service][needed]" value="yes" id="terms_yes">
                                            <label class="form-check-label" for="terms_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[terms_of_service][needed]" value="no" id="terms_no">
                                            <label class="form-check-label" for="terms_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[terms_of_service][source]" value="client" id="terms_client">
                                            <label class="form-check-label" for="terms_client">Client provides</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[terms_of_service][source]" value="generate" id="terms_gen">
                                            <label class="form-check-label" for="terms_gen">Generate template</label>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Cookie Consent Banner</strong><br><small class="text-muted">Required for EU visitors</small></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[cookie_consent][needed]" value="yes" id="cookie_yes">
                                            <label class="form-check-label" for="cookie_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[cookie_consent][needed]" value="no" id="cookie_no">
                                            <label class="form-check-label" for="cookie_no">No</label>
                                        </div>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td><strong>Accessibility (WCAG)</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[accessibility][needed]" value="yes" id="access_yes">
                                            <label class="form-check-label" for="access_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[accessibility][needed]" value="no" id="access_no">
                                            <label class="form-check-label" for="access_no">No</label>
                                        </div>
                                    </td>
                                    <td>
                                        <small>Level:</small>
                                        <div class="d-flex gap-2">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="legal[accessibility][level]" value="A" id="access_a">
                                                <label class="form-check-label" for="access_a">A</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="legal[accessibility][level]" value="AA" id="access_aa">
                                                <label class="form-check-label" for="access_aa">AA</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="legal[accessibility][level]" value="AAA" id="access_aaa">
                                                <label class="form-check-label" for="access_aaa">AAA</label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Industry-specific compliance</strong></td>
                                    <td>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[industry_compliance][needed]" value="yes" id="industry_comp_yes">
                                            <label class="form-check-label" for="industry_comp_yes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="legal[industry_compliance][needed]" value="no" id="industry_comp_no">
                                            <label class="form-check-label" for="industry_comp_no">No</label>
                                        </div>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="legal[industry_compliance][details]" placeholder="Specify"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== SECTION 10: POST-LAUNCH & MAINTENANCE ==================== -->
                <div class="questionnaire-section" data-section="10">
                    <h3 class="section-heading">
                        <i class="bi bi-rocket-takeoff"></i> Section 10: Post-Launch & Maintenance
                    </h3>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Training needed?</label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="handover[training]" value="yes" id="training_yes">
                                    <label class="form-check-label" for="training_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="handover[training]" value="no" id="training_no">
                                    <label class="form-check-label" for="training_no">No</label>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm" name="handover[training_topics]" placeholder="Training for what">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Documentation required?</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="handover[documentation]" value="yes" id="docs_yes">
                                    <label class="form-check-label" for="docs_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="handover[documentation]" value="no" id="docs_no">
                                    <label class="form-check-label" for="docs_no">No</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label">Support period included?</label>
                            <div class="mb-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="handover[support]" value="yes" id="support_yes">
                                    <label class="form-check-label" for="support_yes">Yes</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="handover[support]" value="no" id="support_no">
                                    <label class="form-check-label" for="support_no">No</label>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm" name="handover[support_duration]" placeholder="Support duration">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Future Considerations</h5>

                    <div class="mb-3">
                        <label class="form-label">Expected update frequency</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="future[update_frequency]" value="Rarely" id="freq_rarely">
                                <label class="form-check-label" for="freq_rarely">Rarely</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="future[update_frequency]" value="Monthly" id="freq_monthly">
                                <label class="form-check-label" for="freq_monthly">Monthly</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="future[update_frequency]" value="Weekly" id="freq_weekly">
                                <label class="form-check-label" for="freq_weekly">Weekly</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="potential_features" class="form-label">Potential future features</label>
                        <textarea class="form-control" id="potential_features" name="future[potential_features]" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Growth plans</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="future[growth_plans][]" value="E-commerce" id="growth_ecom">
                                <label class="form-check-label" for="growth_ecom">E-commerce</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="future[growth_plans][]" value="Booking system" id="growth_booking">
                                <label class="form-check-label" for="growth_booking">Booking system</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="future[growth_plans][]" value="Member area" id="growth_member">
                                <label class="form-check-label" for="growth_member">Member area</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="future[growth_plans][]" value="Blog expansion" id="growth_blog">
                                <label class="form-check-label" for="growth_blog">Blog expansion</label>
                            </div>
                        </div>
                        <input type="text" class="form-control mt-2" name="future[growth_plans_other]" placeholder="Other (specify)">
                    </div>
                </div>

                <!-- ==================== SECTION 11: SUMMARY & OUTPUT ==================== -->
                <div class="questionnaire-section" data-section="11">
                    <h3 class="section-heading">
                        <i class="bi bi-check2-square"></i> Section 11: Summary & Output
                    </h3>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="project_name" class="form-label">Project Name</label>
                            <input type="text" class="form-control" id="project_name" name="summary[project_name]">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="summary_client" class="form-label">Client</label>
                            <input type="text" class="form-control" id="summary_client" name="summary[client]">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="summary_total_pages" class="form-label">Total Pages</label>
                            <input type="text" class="form-control" id="summary_total_pages" name="summary[total_pages]" readonly>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="summary_domain" class="form-label">Domain</label>
                            <input type="text" class="form-control" id="summary_domain" name="summary[domain]">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="summary_key_features" class="form-label">Key Features</label>
                        <textarea class="form-control" id="summary_key_features" name="summary[key_features]" rows="2"></textarea>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="summary_tech_stack" class="form-label">Tech Stack</label>
                            <input type="text" class="form-control" id="summary_tech_stack" name="summary[tech_stack]" value="HTML/CSS + PHP" readonly>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="summary_hosting" class="form-label">Hosting (cPanel @)</label>
                            <input type="text" class="form-control" id="summary_hosting" name="summary[hosting]">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Scope Definition</h5>

                    <div class="mb-3">
                        <label for="in_scope" class="form-label">In Scope</label>
                        <p class="text-muted small">List everything included in this project</p>
                        <textarea class="form-control" id="in_scope" name="scope[in_scope]" rows="4"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="out_of_scope" class="form-label">Out of Scope</label>
                        <p class="text-muted small">List explicitly excluded items</p>
                        <textarea class="form-control" id="out_of_scope" name="scope[out_of_scope]" rows="4"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="assumptions" class="form-label">Assumptions</label>
                        <p class="text-muted small">List key assumptions</p>
                        <textarea class="form-control" id="assumptions" name="scope[assumptions]" rows="3"></textarea>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Client Deliverables Checklist</h5>
                    <p class="text-muted small">Items needed from client before development</p>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th style="width: 150px;">Status</th>
                                    <th style="width: 150px;">Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $deliverables = [
                                    'logo' => 'Logo files (SVG/PNG)',
                                    'brand' => 'Brand colors/fonts',
                                    'copy' => 'Page content (copy)',
                                    'images' => 'Images/photos',
                                    'hosting' => 'Hosting credentials',
                                    'domain' => 'Domain access',
                                    'social' => 'Social media URLs',
                                    'contact_info' => 'Contact information',
                                    'legal' => 'Legal pages content'
                                ];
                                foreach ($deliverables as $key => $label):
                                ?>
                                <tr>
                                    <td><?php echo $label; ?></td>
                                    <td>
                                        <select class="form-select form-select-sm" name="deliverables[<?php echo $key; ?>][status]">
                                            <option value="">-- Status --</option>
                                            <option value="Received">Received</option>
                                            <option value="Pending">Pending</option>
                                            <option value="N/A">N/A</option>
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="deliverables[<?php echo $key; ?>][due]"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Additional Notes</h5>
                    <div class="mb-3">
                        <label for="additional_notes" class="form-label">Notes & Additional Information</label>
                        <p class="text-muted small">Space for any additional notes, special requirements, or context</p>
                        <textarea class="form-control" id="additional_notes" name="additional_notes" rows="6"></textarea>
                    </div>
                </div>

                <!-- Form Navigation - Multi-step Controls -->
                <div class="form-navigation">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <button type="button" class="btn btn-secondary btn-lg" id="prevSectionBtn" disabled>
                            <i class="bi bi-arrow-left"></i> Previous
                        </button>
                        <div class="text-center my-2">
                            <span id="sectionIndicator" class="badge bg-primary fs-6">Section 1 of 11</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-lg" id="nextSectionBtn">
                            Next <i class="bi bi-arrow-right"></i>
                        </button>
                        <button type="submit" class="btn btn-success btn-lg" id="submitBtn" style="display: none;">
                            <i class="bi bi-send"></i> Submit Questionnaire
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
// Toast notification function
function showToast(type, message) {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toastId = 'toast-' + Date.now();

    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${type === 'success' ? '<i class="bi bi-check-circle me-2"></i>' : '<i class="bi bi-exclamation-triangle me-2"></i>'}
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Form section navigation
let currentSection = 1;
const totalSections = 11;

function showSection(sectionNum) {
    // Hide all sections
    document.querySelectorAll('.questionnaire-section').forEach(section => {
        section.classList.remove('active');
    });

    // Show current section
    const section = document.querySelector(`[data-section="${sectionNum}"]`);
    if (section) {
        section.classList.add('active');
        // Scroll to top of form smoothly
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Update progress bar
    const percentage = Math.round((sectionNum / totalSections) * 100);
    const progressBar = document.getElementById('formProgress');
    progressBar.style.width = percentage + '%';
    progressBar.setAttribute('aria-valuenow', percentage);
    progressBar.textContent = `Section ${sectionNum} of ${totalSections}`;

    // Update section indicator
    document.getElementById('sectionIndicator').textContent = `Section ${sectionNum} of ${totalSections}`;

    // Update navigation buttons
    document.getElementById('prevSectionBtn').disabled = (sectionNum === 1);

    if (sectionNum === totalSections) {
        document.getElementById('nextSectionBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'block';
    } else {
        document.getElementById('nextSectionBtn').style.display = 'block';
        document.getElementById('submitBtn').style.display = 'none';
    }
}

// Navigation button handlers
document.getElementById('nextSectionBtn').addEventListener('click', function() {
    if (currentSection < totalSections) {
        currentSection++;
        showSection(currentSection);
    }
});

document.getElementById('prevSectionBtn').addEventListener('click', function() {
    if (currentSection > 1) {
        currentSection--;
        showSection(currentSection);
    }
});

// Save draft function
document.getElementById('saveDraftBtn').addEventListener('click', function() {
    const formData = new FormData(document.getElementById('questionnaireForm'));
    formData.set('action', 'save_draft');

    fetch('form_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Draft saved successfully!');
            if (data.submission_id) {
                document.querySelector('[name="submission_id"]').value = data.submission_id;
            }
        } else {
            showToast('danger', data.error || 'Failed to save draft');
        }
    })
    .catch(error => {
        showToast('danger', 'Error saving draft');
        console.error('Error:', error);
    });
});

// Auto-save every 2 minutes
let autoSaveTimer;
function setupAutoSave() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => {
        document.getElementById('saveDraftBtn').click();
        setupAutoSave(); // Re-setup for next interval
    }, 120000); // 2 minutes
}

// Start auto-save on any input change
document.querySelectorAll('input, textarea, select').forEach(element => {
    element.addEventListener('change', setupAutoSave);
});

// ===== Dynamic Competitor Rows =====
let competitorIndex = 3; // Start from 3 since we have 0, 1, 2 already

// Add competitor row
document.getElementById('addCompetitorBtn').addEventListener('click', function() {
    const tbody = document.getElementById('competitorsTableBody');
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-index', competitorIndex);
    newRow.innerHTML = `
        <td><input type="text" class="form-control form-control-sm" name="competitors[${competitorIndex}][name]"></td>
        <td><input type="url" class="form-control form-control-sm" name="competitors[${competitorIndex}][url]" placeholder="https://"></td>
        <td><textarea class="form-control form-control-sm" name="competitors[${competitorIndex}][notes]" rows="2"></textarea></td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-competitor-btn" title="Remove competitor">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    competitorIndex++;

    // Setup auto-save for new inputs
    newRow.querySelectorAll('input, textarea').forEach(el => {
        el.addEventListener('change', setupAutoSave);
    });
});

// Remove competitor row (event delegation)
document.getElementById('competitorsTableBody').addEventListener('click', function(e) {
    const removeBtn = e.target.closest('.remove-competitor-btn');
    if (removeBtn) {
        const tbody = document.getElementById('competitorsTableBody');
        const rowCount = tbody.querySelectorAll('tr').length;

        // Keep at least 1 row
        if (rowCount > 1) {
            removeBtn.closest('tr').remove();
        } else {
            showToast('warning', 'You must keep at least one competitor row');
        }
    }
});

// ===== Dynamic Reference Sites Rows (Section 5) =====
let referenceSiteIndex = 3; // Start from 3 since we have 0, 1, 2 already

// Add reference site row
document.getElementById('addReferenceSiteBtn').addEventListener('click', function() {
    const tbody = document.getElementById('referenceSitesTableBody');
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-index', referenceSiteIndex);
    newRow.innerHTML = `
        <td><input type="url" class="form-control form-control-sm" name="reference_sites[${referenceSiteIndex}][url]" placeholder="https://"></td>
        <td><textarea class="form-control form-control-sm" name="reference_sites[${referenceSiteIndex}][likes]" rows="2"></textarea></td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-reference-site-btn" title="Remove website">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    referenceSiteIndex++;

    // Setup auto-save for new inputs
    newRow.querySelectorAll('input, textarea').forEach(el => {
        el.addEventListener('change', setupAutoSave);
    });
});

// Remove reference site row (event delegation)
document.getElementById('referenceSitesTableBody').addEventListener('click', function(e) {
    const removeBtn = e.target.closest('.remove-reference-site-btn');
    if (removeBtn) {
        const tbody = document.getElementById('referenceSitesTableBody');
        const rowCount = tbody.querySelectorAll('tr').length;

        // Keep at least 1 row
        if (rowCount > 1) {
            removeBtn.closest('tr').remove();
        } else {
            showToast('warning', 'You must keep at least one reference website row');
        }
    }
});

// ===== Dynamic Avoid Sites Rows (Section 5) =====
let avoidSiteIndex = 1; // Start from 1 since we have 0 already

// Add avoid site row
document.getElementById('addAvoidSiteBtn').addEventListener('click', function() {
    const tbody = document.getElementById('avoidSitesTableBody');
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-index', avoidSiteIndex);
    newRow.innerHTML = `
        <td><input type="url" class="form-control form-control-sm" name="avoid_sites[${avoidSiteIndex}][url]" placeholder="https://"></td>
        <td><textarea class="form-control form-control-sm" name="avoid_sites[${avoidSiteIndex}][dislikes]" rows="2"></textarea></td>
        <td class="text-center align-middle">
            <button type="button" class="btn btn-sm btn-outline-danger remove-avoid-site-btn" title="Remove website">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    avoidSiteIndex++;

    // Setup auto-save for new inputs
    newRow.querySelectorAll('input, textarea').forEach(el => {
        el.addEventListener('change', setupAutoSave);
    });
});

// Remove avoid site row (event delegation)
document.getElementById('avoidSitesTableBody').addEventListener('click', function(e) {
    const removeBtn = e.target.closest('.remove-avoid-site-btn');
    if (removeBtn) {
        const tbody = document.getElementById('avoidSitesTableBody');
        const rowCount = tbody.querySelectorAll('tr').length;

        // Keep at least 1 row
        if (rowCount > 1) {
            removeBtn.closest('tr').remove();
        } else {
            showToast('warning', 'You must keep at least one avoid website row');
        }
    }
});

// Initialize
showSection(1);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
